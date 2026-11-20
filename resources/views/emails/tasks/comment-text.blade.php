{{ __('Hola') }} {{ $recipient->name }},

{{ __('Hay una nueva actividad en una tarea de tu área.') }}

{{ __('Tarea') }}: {{ $task->title }}
{{ __('Estado') }}: {{ $task->status->label() }}
{{ __('Autor') }}: {{ $comment->user?->name ?? __('Sistema') }}

{{ $comment->body ?: __('Se adjuntaron archivos.') }}

@if ($comment->attachments->isNotEmpty())
{{ __('Archivos adjuntos') }}:
@foreach ($comment->attachments as $attachment)
- {{ $attachment->original_name }}
@endforeach
@endif
