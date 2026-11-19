<div>
    <p>{{ __('Hola') }} {{ $ticket->requester_name ?: __('usuario') }},</p>

    <p>{{ __('Tu ticket ha sido asignado al área correspondiente.') }}</p>

    <ul>
        <li>{{ __('Código') }}: {{ $ticket->code }}</li>
        <li>{{ __('Asunto') }}: {{ $ticket->subject }}</li>
        <li>{{ __('Área') }}: {{ $ticket->area?->name ?? __('Por definir') }}</li>
        <li>{{ __('Responsable') }}: {{ $ticket->assignee?->name ?? __('Por definir') }}</li>
    </ul>

    <p>{{ __('Gracias por tu paciencia.') }}</p>
</div>
