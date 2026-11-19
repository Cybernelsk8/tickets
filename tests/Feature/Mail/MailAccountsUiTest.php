<?php

use App\Jobs\SyncMailAccountJob;
use App\Livewire\Mail\Accounts;
use App\Models\MailAccount;
use App\Models\User;
use App\Support\Mail\MailClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeMailClient;

it('encrypts credentials when a mail account is created', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $client = new FakeMailClient;
    $this->app->instance(MailClient::class, $client);

    Livewire::actingAs($admin)->test(Accounts::class)
        ->set([
            'name' => 'Soporte principal',
            'email' => 'soporte@example.com',
            'username' => 'soporte',
            'password' => 'super-secreto',
            'imapHost' => 'imap.example.com',
            'smtpHost' => 'smtp.example.com',
        ])
        ->call('testConnection')
        ->call('save')
        ->assertHasNoErrors();

    $account = MailAccount::query()->firstOrFail();

    expect($account->credentials)->toBe('super-secreto')
        ->and(DB::table('mail_accounts')->value('credentials'))->not->toBe('super-secreto')
        ->and($account->toArray())->not->toHaveKey('credentials');
    expect($client->connectionChecks)->toBe(1);
});

it('preserves the stored password when the edit form leaves it blank', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create(['credentials' => 'original-pass']);

    Livewire::actingAs($admin)->test(Accounts::class)
        ->call('edit', $account->id)
        ->set('name', 'Cuenta actualizada')
        ->set('password', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->name)->toBe('Cuenta actualizada')
        ->and($account->fresh()->credentials)->toBe('original-pass');
});

it('rejects an unsupported encryption mode', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(Accounts::class)
        ->set([
            'name' => 'Cuenta inválida',
            'email' => 'soporte@example.com',
            'username' => 'soporte',
            'password' => 'secreto',
            'imapHost' => 'imap.example.com',
            'smtpHost' => 'smtp.example.com',
            'imapEncryption' => 'ftp',
        ])
        ->call('save')
        ->assertHasErrors('imapEncryption');

    expect(MailAccount::query()->count())->toBe(0);
});

it('does not show the account form to unprivileged users', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Accounts::class)
        ->assertForbidden();
});

it('queues a synchronization job from the account form', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create();
    Queue::fake([SyncMailAccountJob::class]);

    Livewire::actingAs($admin)->test(Accounts::class)->call('syncNow', $account->id);

    Queue::assertPushed(SyncMailAccountJob::class, fn (SyncMailAccountJob $job): bool => $job->mailAccountId === $account->id);
});

it('queues only active accounts from the sync command', function () {
    $active = MailAccount::factory()->create();
    $inactive = MailAccount::factory()->inactive()->create();
    Queue::fake([SyncMailAccountJob::class]);

    Artisan::call('tickets:sync-mail');

    Queue::assertPushed(SyncMailAccountJob::class, fn (SyncMailAccountJob $job): bool => $job->mailAccountId === $active->id);
    Queue::assertNotPushed(SyncMailAccountJob::class, fn (SyncMailAccountJob $job): bool => $job->mailAccountId === $inactive->id);
});

it('queues only accounts whose sync interval has elapsed', function () {
    $recent = MailAccount::factory()->create(['last_synced_at' => now()]);
    $due = MailAccount::factory()->create([
        'last_synced_at' => now()->subHour(),
        'sync_every_minutes' => 15,
    ]);
    Queue::fake([SyncMailAccountJob::class]);

    Artisan::call('tickets:sync-mail --due');

    Queue::assertPushed(SyncMailAccountJob::class, fn (SyncMailAccountJob $job): bool => $job->mailAccountId === $due->id);
    Queue::assertNotPushed(SyncMailAccountJob::class, fn (SyncMailAccountJob $job): bool => $job->mailAccountId === $recent->id);
});
