<?php

namespace App\Livewire\Mail;

use App\Jobs\SyncMailAccountJob;
use App\Models\MailAccount;
use App\Models\User;
use App\Support\Mail\MailClient;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Accounts extends Component
{
    public ?int $accountId = null;

    public string $name = '';

    public string $email = '';

    public string $imapHost = '';

    public int $imapPort = 993;

    public string $imapEncryption = 'ssl';

    public string $smtpHost = '';

    public int $smtpPort = 587;

    public string $smtpEncryption = 'tls';

    public string $username = '';

    public string $password = '';

    public ?int $ownerUserId = null;

    public bool $isActive = true;

    public int $syncEveryMinutes = 15;

    public function mount(): void
    {
        Gate::authorize('viewAny', MailAccount::class);
    }

    /**
     * @return Collection<int, MailAccount>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return MailAccount::query()
            ->withCount('messages')
            ->latest()
            ->get()
            ->filter(fn (MailAccount $account): bool => Gate::allows('view', $account))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $account = $this->accountId === null ? new MailAccount : MailAccount::findOrFail($this->accountId);

        if ($account->exists) {
            Gate::authorize('update', $account);
        } else {
            Gate::authorize('create', MailAccount::class);
        }

        $account->fill($this->accountData($account));
        $account->save();

        Flux::toast(text: __('Cuenta de correo guardada.'), variant: 'success');
        $this->resetForm();
    }

    public function edit(int $accountId): void
    {
        $account = MailAccount::findOrFail($accountId);
        Gate::authorize('view', $account);

        $this->accountId = $account->id;
        $this->name = $account->name;
        $this->email = $account->email;
        $this->imapHost = $account->imap_host;
        $this->imapPort = $account->imap_port;
        $this->imapEncryption = $account->imap_encryption;
        $this->smtpHost = $account->smtp_host;
        $this->smtpPort = $account->smtp_port;
        $this->smtpEncryption = $account->smtp_encryption;
        $this->username = $account->username;
        $this->password = '';
        $this->ownerUserId = $account->owner_user_id;
        $this->isActive = $account->is_active;
        $this->syncEveryMinutes = $account->sync_every_minutes;
    }

    public function testConnection(MailClient $mailClient): void
    {
        $this->validate($this->rules());

        $account = $this->accountId === null ? new MailAccount : MailAccount::findOrFail($this->accountId);

        if ($account->exists) {
            Gate::authorize('view', $account);
        } else {
            Gate::authorize('create', MailAccount::class);
        }

        $account->fill($this->accountData($account));

        if ($mailClient->testConnection($account)) {
            Flux::toast(text: __('Conexión IMAP correcta.'), variant: 'success');

            return;
        }

        Flux::toast(text: __('No se pudo conectar al servidor IMAP.'), variant: 'danger');
    }

    public function syncNow(int $accountId): void
    {
        $account = MailAccount::findOrFail($accountId);
        Gate::authorize('sync', $account);

        SyncMailAccountJob::dispatch($account->id);

        Flux::toast(text: __('Sincronización encolada.'), variant: 'success');
    }

    public function delete(int $accountId): void
    {
        $account = MailAccount::findOrFail($accountId);
        Gate::authorize('delete', $account);

        $account->delete();

        if ($this->accountId === $accountId) {
            $this->resetForm();
        }

        Flux::toast(text: __('Cuenta de correo eliminada.'), variant: 'success');
    }

    public function resetForm(): void
    {
        $this->accountId = null;
        $this->name = '';
        $this->email = '';
        $this->imapHost = '';
        $this->imapPort = 993;
        $this->imapEncryption = 'ssl';
        $this->smtpHost = '';
        $this->smtpPort = 587;
        $this->smtpEncryption = 'tls';
        $this->username = '';
        $this->password = '';
        $this->ownerUserId = null;
        $this->isActive = true;
        $this->syncEveryMinutes = 15;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'imapHost' => ['required', 'string', 'max:255'],
            'imapPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'imapEncryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtpEncryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$this->accountId === null ? 'required' : 'nullable', 'string', 'max:255'],
            'ownerUserId' => ['nullable', 'exists:users,id'],
            'isActive' => ['boolean'],
            'syncEveryMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountData(MailAccount $account): array
    {
        return [
            'name' => $this->name,
            'email' => mb_strtolower($this->email),
            'imap_host' => $this->imapHost,
            'imap_port' => $this->imapPort,
            'imap_encryption' => $this->imapEncryption,
            'smtp_host' => $this->smtpHost,
            'smtp_port' => $this->smtpPort,
            'smtp_encryption' => $this->smtpEncryption,
            'username' => $this->username,
            'credentials' => filled($this->password) ? $this->password : ($account->credentials ?? ''),
            'owner_user_id' => $this->ownerUserId,
            'is_active' => $this->isActive,
            'sync_every_minutes' => $this->syncEveryMinutes,
        ];
    }
}
