<?php

namespace App\Actions\Tickets;

use App\Events\TicketAssigned;
use App\Models\Area;
use App\Models\TaskStatusTransition;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTicket
{
    /**
     * Assign a ticket to an area and create or update its root task.
     *
     * @param  array{area_id: int, category_id: int, priority: TicketPriority, assignee_id: int|null}  $data
     */
    public function handle(Ticket $ticket, array $data): Ticket
    {
        Gate::authorize('assign', $ticket);

        $area = Area::query()->findOrFail($data['area_id']);
        $category = TicketCategory::query()->findOrFail($data['category_id']);
        $priority = $data['priority'];
        $assignee = $data['assignee_id'] === null
            ? null
            : User::query()->findOrFail($data['assignee_id']);

        if ($assignee !== null && $assignee->area_id !== $area->id) {
            throw ValidationException::withMessages([
                'assignee_id' => __('El responsable debe pertenecer al área seleccionada.'),
            ]);
        }

        $assignedAt = $ticket->assigned_at ?? now();

        DB::transaction(function () use ($ticket, $area, $category, $priority, $assignee, $assignedAt): void {
            $ticket->update([
                'area_id' => $area->id,
                'category_id' => $category->id,
                'priority' => $priority,
                'assignee_id' => $assignee?->id,
                'status' => TaskStatus::Assigned,
                'assigned_at' => $assignedAt,
            ]);

            $rootTask = $ticket->rootTask;

            if ($rootTask === null) {
                $rootTask = $ticket->tasks()->create([
                    'parent_id' => null,
                    'title' => sprintf('%s — %s', $ticket->code, $ticket->subject),
                    'description' => $ticket->description,
                    'area_id' => $area->id,
                    'assignee_id' => $assignee?->id,
                    'priority' => $priority,
                    'status' => TaskStatus::Todo,
                    'position' => 0,
                    'created_by' => auth()->id(),
                ]);

                TaskStatusTransition::create([
                    'task_id' => $rootTask->id,
                    'from_status' => null,
                    'to_status' => TaskStatus::Todo->value,
                    'changed_by' => auth()->id(),
                    'occurred_at' => now(),
                    'note' => __('Tarea raíz creada al asignar el ticket.'),
                ]);
            } else {
                $rootTask->update([
                    'area_id' => $area->id,
                    'assignee_id' => $assignee?->id,
                    'priority' => $priority,
                ]);
            }
        });

        TicketAssigned::dispatch($ticket->fresh(), $assignee);

        return $ticket->fresh();
    }
}
