<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    /**
     * Stream a private attachment after verifying access to its parent.
     */
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $fileName = Str::ascii(basename(str_replace('\\', '/', $attachment->original_name))) ?: 'attachment';

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $fileName,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
        );
    }
}
