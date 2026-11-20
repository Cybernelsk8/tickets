<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\AssignTicket;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TicketPriority;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $assigningTicketId = null;

    public ?int $areaId = null;

    public ?int $categoryId = null;

    public string $priority = 'medium';

    public ?int $assigneeId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Ticket::class);
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['area', 'category', 'assignee', 'mailMessage'])
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('requester_email', 'like', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Area>
     */
    #[Computed]
    public function areas(): Collection
    {
        return Area::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, TicketCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return TicketCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignees(): Collection
    {
        if ($this->areaId === null) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('area_id', $this->areaId)
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAreaId(): void
    {
        $this->assigneeId = null;
    }

    public function openAssign(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        Gate::authorize('assign', $ticket);

        $this->assigningTicketId = $ticket->id;
        $this->areaId = $ticket->area_id;
        $this->categoryId = $ticket->category_id;
        $this->priority = $ticket->priority->value;
        $this->assigneeId = $ticket->assignee_id;
    }

    public function assign(AssignTicket $action): void
    {
        if ($this->assigningTicketId === null) {
            return;
        }

        $ticket = Ticket::findOrFail($this->assigningTicketId);
        Gate::authorize('assign', $ticket);

        $validated = $this->validate([
            'areaId' => ['required', 'integer', 'exists:areas,id'],
            'categoryId' => ['required', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['required', Rule::in(array_map(
                fn (TicketPriority $case): string => $case->value,
                TicketPriority::cases(),
            ))],
            'assigneeId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($validated['assigneeId'] !== null
            && User::query()->whereKey($validated['assigneeId'])->value('area_id') !== $validated['areaId']) {
            $this->addError('assigneeId', __('El responsable debe pertenecer al área seleccionada.'));

            return;
        }

        $action->handle($ticket, [
            'area_id' => $validated['areaId'],
            'category_id' => $validated['categoryId'],
            'priority' => TicketPriority::from($validated['priority']),
            'assignee_id' => $validated['assigneeId'],
        ]);

        $this->reset('assigningTicketId', 'areaId', 'categoryId', 'priority', 'assigneeId');

        Flux::toast(text: __('Ticket asignado y remitente notificado.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.tickets.index');
    }
}
