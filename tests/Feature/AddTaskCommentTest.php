<?php

use App\Actions\Tasks\AddTaskComment;
use App\Mail\TaskCommentMail;
use App\Models\Area;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

it('stores a collaborator comment with a private attachment and notifies the manager', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::InProgress,
    ]);

    Mail::fake();
    $this->actingAs($collaborator);

    $comment = app(AddTaskComment::class)->handle(
        $task,
        'Captura del avance',
        false,
        [UploadedFile::fake()->create('evidencia.pdf', 32, 'application/pdf')],
    );

    expect($comment->body)->toBe('Captura del avance')
        ->and($comment->user_id)->toBe($collaborator->id)
        ->and($comment->is_internal)->toBeFalse();

    $attachment = $comment->attachments()->firstOrFail();

    expect($attachment->original_name)->toBe('evidencia.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->checksum)->not->toBeNull();

    Storage::disk('local')->assertExists($attachment->path);
    Mail::assertQueued(TaskCommentMail::class, 1);
});

it('hides internal comments from collaborators but not from their manager', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $collaborator->id]);

    $this->actingAs($manager);

    $comment = app(AddTaskComment::class)->handle($task, 'Observación del jefe', true);

    expect($comment->is_internal)->toBeTrue()
        ->and(Gate::forUser($manager)->allows('view', $comment))->toBeTrue()
        ->and(Gate::forUser($collaborator)->allows('view', $comment))->toBeFalse();
});

it('rejects attachments with a disallowed mime type without storing anything', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $collaborator->id]);

    $this->actingAs($collaborator);

    expect(fn () => app(AddTaskComment::class)->handle(
        $task,
        'Archivo sospechoso',
        false,
        [UploadedFile::fake()->create('virus.exe', 16, 'application/x-msdownload')],
    ))->toThrow(ValidationException::class);

    expect(Comment::count())->toBe(0);
});

it('rejects attachments larger than the configured limit', function () {
    config()->set('tickets.attachments.max_kb', 1);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $collaborator->id]);

    $this->actingAs($collaborator);

    expect(fn () => app(AddTaskComment::class)->handle(
        $task,
        'Evidencia pesada',
        false,
        [UploadedFile::fake()->create('grande.pdf', 128, 'application/pdf')],
    ))->toThrow(ValidationException::class);

    expect(Comment::count())->toBe(0);
});
