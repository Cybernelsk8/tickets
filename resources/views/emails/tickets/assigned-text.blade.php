{{ __('Hola') }} {{ $ticket->requester_name ?: __('usuario') }},

{{ __('Tu ticket ha sido asignado al área correspondiente.') }}

{{ __('Código') }}: {{ $ticket->code }}
{{ __('Asunto') }}: {{ $ticket->subject }}
{{ __('Área') }}: {{ $ticket->area?->name ?? __('Por definir') }}
{{ __('Responsable') }}: {{ $ticket->assignee?->name ?? __('Por definir') }}

{{ __('Gracias por tu paciencia.') }}
