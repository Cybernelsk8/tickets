<div>
    <p>{{ __('Hola') }} {{ $recipient->name }},</p>

    <p>{{ __('Hay una nueva actividad en una tarea de tu área.') }}</p>

    <ul>
        <li>{{ __('Tarea') }}: {{ $task->title }}</li>
        <li>{{ __('Estado') }}: {{ $task->status->label() }}</li>
        <li>{{ __('Autor') }}: {{ $comment->user?->name ?? __('Sistema') }}</li>
        @if ($comment->is_internal)
            <li>{{ __('Tipo') }}: {{ __('Comentario interno') }}</li>
        @endif
    </ul>

    <p>{{ $comment->body ?: __('Se adjuntaron archivos.') }}</p>

    @if ($comment->attachments->isNotEmpty())
        <p>{{ __('Archivos adjuntos') }}:</p>
        <ul>
            @foreach ($comment->attachments as $attachment)
                <li>{{ $attachment->original_name }}</li>
            @endforeach
        </ul>
    @endif
</div>
