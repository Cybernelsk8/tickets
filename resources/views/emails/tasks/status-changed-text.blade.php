{{ __('Hola') }} {{ $recipient->name }},

{{ __('Una tarea de tu área cambió de estado.') }}

{{ __('Tarea') }}: {{ $task->title }}
{{ __('Estado anterior') }}: {{ $previousStatus->label() }}
{{ __('Estado actual') }}: {{ $status->label() }}
{{ __('Responsable') }}: {{ $task->assignee?->name ?? __('Sin responsable') }}
