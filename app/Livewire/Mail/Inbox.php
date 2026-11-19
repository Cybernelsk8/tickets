<?php

namespace App\Livewire\Mail;

use App\Actions\Tickets\CreateTicketFromMessage;
use App\Jobs\SyncMailAccountJob;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\Ticket;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Inbox extends Component
{
    use WithPagination;

    public ?int $selectedAccountId = null;

    public string $search = '';

    public bool $unreadOnly = false;

    public ?int $selectedMessageId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', MailAccount::class);

        $this->selectedAccountId = $this->accounts()->first()?->id;
    }

    /**
     * @return Collection<int, MailAccount>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return MailAccount::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (MailAccount $account): bool => Gate::allows('view', $account))
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, MailMessage>
     */
    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        if ($this->selectedAccountId === null) {
            return MailMessage::query()->whereKey(null)->paginate(15);
        }

        return MailMessage::query()
            ->where('mail_account_id', $this->selectedAccountId)
            ->withCount('replies')
            ->with('attachments')
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', $search)
                        ->orWhere('from_email', 'like', $search)
                        ->orWhere('from_name', 'like', $search);
                });
            })
            ->when($this->unreadOnly, fn ($query) => $query->where('is_read', false))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function selectedAccount(): ?MailAccount
    {
        if ($this->selectedAccountId === null) {
            return null;
        }

        $account = MailAccount::find($this->selectedAccountId);

        return $account !== null && Gate::allows('view', $account) ? $account : null;
    }

    #[Computed]
    public function selectedMessage(): ?MailMessage
    {
        if ($this->selectedMessageId === null) {
            return null;
        }

        $message = MailMessage::query()
            ->with(['attachments', 'replies.attachments'])
            ->find($this->selectedMessageId);

        if ($message === null
            || $message->mail_account_id !== $this->selectedAccountId
            || Gate::denies('view', $message)) {
            return null;
        }

        return $message;
    }

    #[Computed]
    public function selectedTicket(): ?Ticket
    {
        return $this->selectedMessage()?->ticket;
    }

    public function createTicket(CreateTicketFromMessage $action): void
    {
        $message = $this->selectedMessage();

        if ($message === null) {
            return;
        }

        $ticket = $action->handle($message, auth()->id());

        Flux::toast(text: __('Ticket :code creado.', ['code' => $ticket->code]), variant: 'success');
    }

    public function selectAccount(int $accountId): void
    {
        $account = MailAccount::findOrFail($accountId);
        Gate::authorize('view', $account);

        $this->selectedAccountId = $account->id;
        $this->selectedMessageId = null;
        $this->resetPage();
    }

    public function selectMessage(int $messageId): void
    {
        $message = MailMessage::findOrFail($messageId);

        if ($message->mail_account_id !== $this->selectedAccountId) {
            abort(403);
        }

        Gate::authorize('view', $message);

        $this->selectedMessageId = $message->id;

        if (! $message->is_read) {
            $message->update(['is_read' => true]);
        }
    }

    public function syncSelected(): void
    {
        if ($this->selectedAccountId === null) {
            return;
        }

        $account = MailAccount::findOrFail($this->selectedAccountId);
        Gate::authorize('sync', $account);

        SyncMailAccountJob::dispatch($account->id);

        Flux::toast(text: __('Sincronización encolada.'), variant: 'success');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.mail.inbox');
    }
}
