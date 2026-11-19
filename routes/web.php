<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Livewire\Boards\Index as BoardsIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Mail\Accounts as MailAccounts;
use App\Livewire\Mail\Inbox as MailInbox;
use App\Livewire\Tasks\Mine as TasksMine;
use App\Livewire\Tickets\Index as TicketsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified_if_required'])->group(function () {
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');
    Route::livewire('mail/inbox', MailInbox::class)->name('mail.inbox');
    Route::livewire('mail/accounts', MailAccounts::class)->name('mail.accounts.index');
    Route::livewire('tickets', TicketsIndex::class)->name('tickets.index');
    Route::livewire('boards', BoardsIndex::class)->name('boards.index');
    Route::livewire('tasks/mine', TasksMine::class)->name('tasks.mine');
    Route::get('attachments/{attachment}', AttachmentDownloadController::class)->name('attachments.download');
});

require __DIR__.'/settings.php';
