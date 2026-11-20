<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Livewire\Admin\Roles\Index as AdminRolesIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Boards\Index as BoardsIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Mail\Accounts as MailAccounts;
use App\Livewire\Mail\Inbox as MailInbox;
use App\Livewire\Tickets\Index as TicketsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified_if_required'])->group(function () {
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');
    Route::livewire('mail/inbox', MailInbox::class)->name('mail.inbox');
    Route::livewire('mail/accounts', MailAccounts::class)->name('mail.accounts.index');
    Route::livewire('tickets', TicketsIndex::class)->name('tickets.index');
    Route::livewire('boards', BoardsIndex::class)->name('boards.index');
    Route::get('tasks/mine', fn () => redirect()->route('boards.index', ['scope' => 'mine']))->name('tasks.mine');
    Route::livewire('admin/users', AdminUsersIndex::class)->name('admin.users.index');
    Route::livewire('admin/roles', AdminRolesIndex::class)->name('admin.roles.index');
    Route::get('attachments/{attachment}', AttachmentDownloadController::class)->name('attachments.download');
});

require __DIR__.'/settings.php';
