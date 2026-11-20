<?php

namespace App\Actions\Tasks;

use App\Mail\TaskCommentMail;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddTaskComment
{
    /**
     * Add a comment and its private attachments to a task, then notify the supervisor.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Task $task, string $body, bool $isInternal, array $files = []): Comment
    {
        $user = auth()->user();

        Gate::authorize('view', $task);
        Gate::authorize('create', Comment::class);

        $body = trim($body);

        if ($body === '' && $files === []) {
            throw ValidationException::withMessages([
                'commentBody' => __('Escribe un comentario o adjunta al menos un archivo.'),
            ]);
        }

        if (mb_strlen($body) > 10000) {
            throw ValidationException::withMessages([
                'commentBody' => __('El comentario no puede superar los 10000 caracteres.'),
            ]);
        }

        if ($isInternal && ! $user instanceof User) {
            $isInternal = false;
        }

        if ($isInternal && ! $user->can('comments.viewInternal') && ! $user->managedAreas()->whereKey($task->area_id)->exists()) {
            $isInternal = false;
        }

        $validatedFiles = $this->validateFiles($files);

        $comment = DB::transaction(function () use ($task, $user, $body, $isInternal, $validatedFiles): Comment {
            $comment = Comment::create([
                'commentable_type' => $task->getMorphClass(),
                'commentable_id' => $task->id,
                'user_id' => $user instanceof User ? $user->id : null,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            foreach ($validatedFiles as $file) {
                $this->storeAttachment($comment, $file, $user instanceof User ? $user->id : null);
            }

            return $comment;
        });

        $this->notifySupervisor($task, $comment);

        return $comment->load('attachments');
    }

    /**
     * Validate the real MIME type and size of every uploaded file.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{file: UploadedFile, extension: string}>
     */
    private function validateFiles(array $files): array
    {
        $allowedMimes = config('tickets.attachments.allowed_mimes', []);
        $maxBytes = (int) config('tickets.attachments.max_kb', 10240) * 1024;
        $validated = [];

        foreach ($files as $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    'commentFiles' => __('Uno de los archivos no es válido.'),
                ]);
            }

            $mimeType = $file->getMimeType() ?: '';

            if (! array_key_exists($mimeType, $allowedMimes)) {
                throw ValidationException::withMessages([
                    'commentFiles' => __('El tipo de archivo :mime no está permitido.', ['mime' => $mimeType]),
                ]);
            }

            if ($file->getSize() > $maxBytes) {
                throw ValidationException::withMessages([
                    'commentFiles' => __('El archivo :name supera el tamaño máximo permitido.', [
                        'name' => $file->getClientOriginalName(),
                    ]),
                ]);
            }

            $validated[] = ['file' => $file, 'extension' => $allowedMimes[$mimeType]];
        }

        return $validated;
    }

    /**
     * Persist an uploaded file on the private disk and index it as an attachment.
     *
     * @param  array{file: UploadedFile, extension: string}  $upload
     */
    private function storeAttachment(Comment $comment, array $upload, ?int $uploadedBy): void
    {
        $file = $upload['file'];
        $disk = (string) config('tickets.attachments.disk', 'local');
        $directory = sprintf('task-comments/%d/%d/%d', $comment->commentable_id, now()->year, now()->month);
        $fileName = Str::random(40).'.'.$upload['extension'];

        $path = $file->storeAs($directory, $fileName, $disk);

        Attachment::create([
            'attachable_type' => $comment->getMorphClass(),
            'attachable_id' => $comment->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'source' => 'upload',
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Notify the area manager about the new activity, unless they wrote it.
     */
    private function notifySupervisor(Task $task, Comment $comment): void
    {
        $manager = $task->area?->manager;

        if (! $manager instanceof User || $manager->id === $comment->user_id) {
            return;
        }

        Mail::to($manager->email)->queue(new TaskCommentMail($task, $comment, $manager));
    }
}
