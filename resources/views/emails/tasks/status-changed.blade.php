<div>
    <p>{{ __('Hola') }} {{ $recipient->name }},</p>

    <p>{{ __('Una tarea de tu área cambió de estado.') }}</p>

    <ul>
        <li>{{ __('Tarea') }}: {{ $task->title }}</li>
        <li>{{ __('Estado anterior') }}: {{ $previousStatus->label() }}</li>
        <li>{{ __('Estado actual') }}: {{ $status->label() }}</li>
        <li>{{ __('Responsable') }}: {{ $task->assignee?->name ?? __('Sin responsable') }}</li>
    </ul>
</div>
