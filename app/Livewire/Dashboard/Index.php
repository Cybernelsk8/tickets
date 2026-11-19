<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Models\Ticket;
use App\TaskStatus;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard.index', [
            'totalTickets' => Ticket::count(),
            'openTickets' => Ticket::whereNot('status', TaskStatus::Done->value)->count(),
            'completedTasks' => Task::where('status', TaskStatus::Done->value)->count(),
            'inProgressTasks' => Task::where('status', TaskStatus::InProgress->value)->count(),
        ]);
    }
}
