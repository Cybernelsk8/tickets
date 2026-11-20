<?php

use App\Actions\Tickets\AssignTicket;
use App\Mail\TicketAssignedMail;
use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('assigns a ticket, creates its root task and queues the notification', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $assignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'requester_name' => 'Ana Pérez',
        'requester_email' => 'ana@example.com',
        'area_id' => null,
        'category_id' => null,
        'assigned_at' => null,
    ]);

    Mail::fake();
    $this->actingAs($manager);

    $assigned = app(AssignTicket::class)->handle($ticket, [
        'area_id' => $area->id,
        'category_id' => $category->id,
        'priority' => TicketPriority::High,
        'assignee_id' => $assignee->id,
    ]);

    expect($assigned->status)->toBe(TaskStatus::Assigned)
        ->and($assigned->priority)->toBe(TicketPriority::High)
        ->and($assigned->assigned_at)->not->toBeNull();

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
        'status' => 'assigned',
    ]);

    $rootTask = $assigned->rootTask;

    expect($rootTask)->not->toBeNull();

    $this->assertDatabaseHas('tasks', [
        'id' => $rootTask->id,
        'ticket_id' => $ticket->id,
        'parent_id' => null,
        'area_id' => $area->id,
        'assignee_id' => $assignee->id,
        'status' => 'todo',
    ]);
    $this->assertDatabaseHas('task_status_transitions', [
        'task_id' => $rootTask->id,
        'from_status' => null,
        'to_status' => 'todo',
    ]);

    Mail::assertQueued(TicketAssignedMail::class, function (TicketAssignedMail $mail) use ($ticket): bool {
        return $mail->hasTo('ana@example.com') && $mail->ticket->id === $ticket->id;
    });
});

it('reuses the root task and notifies again on reassignment', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $firstAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $secondAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create(['requester_email' => 'ana@example.com']);

    Mail::fake();
    $this->actingAs($manager);

    $action = app(AssignTicket::class);
    $data = [
        'area_id' => $area->id,
        'category_id' => $category->id,
        'priority' => TicketPriority::Medium,
        'assignee_id' => $firstAssignee->id,
    ];
    $action->handle($ticket, $data);
    $rootTaskId = $ticket->fresh()->rootTask->id;

    $data['assignee_id'] = $secondAssignee->id;
    $action->handle($ticket->fresh(), $data);

    expect($ticket->fresh()->tasks()->whereNull('parent_id')->count())->toBe(1)
        ->and($ticket->fresh()->rootTask->id)->toBe($rootTaskId)
        ->and($ticket->fresh()->rootTask->assignee_id)->toBe($secondAssignee->id);

    Mail::assertQueued(TicketAssignedMail::class, 2);
});

it('forbids an unprivileged user from assigning a ticket', function () {
    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($collaborator);

    app(AssignTicket::class)->handle($ticket, [
        'area_id' => $area->id,
        'category_id' => $category->id,
        'priority' => TicketPriority::Medium,
        'assignee_id' => null,
    ]);
})->throws(AuthorizationException::class);

it('forbids an area manager from assigning a ticket of another area', function () {
    $manager = User::factory()->jefeArea()->create();
    $managedArea = Area::factory()->create(['manager_id' => $manager->id]);
    $otherArea = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create(['area_id' => $otherArea->id]);

    $this->actingAs($manager);

    app(AssignTicket::class)->handle($ticket, [
        'area_id' => $otherArea->id,
        'category_id' => $category->id,
        'priority' => TicketPriority::Medium,
        'assignee_id' => null,
    ]);
})->throws(AuthorizationException::class);

it('rejects an assignee outside the selected area without side effects', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $outsideAssignee = User::factory()->colaborador()->create(['area_id' => $otherArea->id]);
    $ticket = Ticket::factory()->create(['status' => TaskStatus::Todo, 'assigned_at' => null]);

    Mail::fake();
    $this->actingAs($manager);

    try {
        app(AssignTicket::class)->handle($ticket, [
            'area_id' => $area->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Medium,
            'assignee_id' => $outsideAssignee->id,
        ]);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('assignee_id');
    }

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'todo', 'assigned_at' => null]);
    Mail::assertNothingQueued();
});

it('refuses to reassign a finished ticket without side effects', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $originalAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $replacement = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $category->id,
        'status' => TaskStatus::Done,
        'assignee_id' => $originalAssignee->id,
        'assigned_at' => now()->subDay(),
        'closed_at' => now(),
    ]);
    $rootTask = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $originalAssignee->id,
        'parent_id' => null,
    ]);

    Mail::fake();
    $this->actingAs($manager);

    try {
        app(AssignTicket::class)->handle($ticket, [
            'area_id' => $area->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Medium,
            'assignee_id' => $replacement->id,
        ]);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('ticket');
    }

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $originalAssignee->id,
        'status' => 'done',
    ]);
    $this->assertDatabaseHas('tasks', ['id' => $rootTask->id, 'assignee_id' => $originalAssignee->id]);
    Mail::assertNothingQueued();
});

it('lets a super administrator force reassignment of a finished ticket', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $originalAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $replacement = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Done,
        'assignee_id' => $originalAssignee->id,
        'assigned_at' => now()->subDay(),
        'closed_at' => now(),
    ]);
    $rootTask = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $originalAssignee->id,
        'parent_id' => null,
    ]);

    Mail::fake();
    $this->actingAs($admin);

    $assigned = app(AssignTicket::class)->handle($ticket, [
        'area_id' => $area->id,
        'category_id' => $category->id,
        'priority' => TicketPriority::High,
        'assignee_id' => $replacement->id,
    ]);

    expect($assigned->status)->toBe(TaskStatus::Assigned)
        ->and($assigned->assignee_id)->toBe($replacement->id);

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'assignee_id' => $replacement->id,
        'status' => 'assigned',
    ]);
    $this->assertDatabaseHas('tasks', ['id' => $rootTask->id, 'assignee_id' => $replacement->id]);
    Mail::assertQueued(TicketAssignedMail::class, 1);
});
