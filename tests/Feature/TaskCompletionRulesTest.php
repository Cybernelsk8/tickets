<?php

use App\Actions\Tasks\MoveTask;
use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

it('keeps the root task and ticket open when its last subtask is finished', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Assigned,
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
    ]);
    $subtask = Task::factory()->childOf($root)->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
    ]);

    $this->actingAs($manager);

    app(MoveTask::class)->handle($subtask, TaskStatus::Done);

    expect($root->fresh()->status)->toBe(TaskStatus::Testing)
        ->and($ticket->fresh()->status)->toBe(TaskStatus::Assigned)
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

it('blocks the final approval until every subtask is done and then closes the ticket', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Assigned,
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
    ]);
    Task::factory()->childOf($root)->done()->create(['area_id' => $area->id]);
    $pendingSubtask = Task::factory()->childOf($root)->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(MoveTask::class)->handle($root, TaskStatus::Done))
        ->toThrow(ValidationException::class);

    $pendingSubtask->update([
        'status' => TaskStatus::Done,
        'completed_at' => now(),
    ]);

    $moved = app(MoveTask::class)->handle($root, TaskStatus::Done);

    expect($moved->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('does not let a collaborator approve a root that has subtasks', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $root = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::Testing,
    ]);
    Task::factory()->childOf($root)->done()->create(['area_id' => $area->id]);

    $this->actingAs($collaborator);

    expect(fn () => app(MoveTask::class)->handle($root, TaskStatus::Done))
        ->toThrow(AuthorizationException::class);
});

it('lets a collaborator finish a directly assigned root and closes its ticket', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Assigned,
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::Testing,
    ]);

    $this->actingAs($collaborator);

    $moved = app(MoveTask::class)->handle($root, TaskStatus::Done);

    expect($moved->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});
