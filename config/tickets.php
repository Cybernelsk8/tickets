<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evidencia antes de terminar
    |--------------------------------------------------------------------------
    |
    | Cuando está activo, una tarea necesita al menos un comentario o adjunto
    | para poder moverse al estado "Terminado".
    |
    */

    'require_evidence_before_done' => env('TICKETS_REQUIRE_EVIDENCE_BEFORE_DONE', false),

    /*
    |--------------------------------------------------------------------------
    | Adjuntos de comentarios
    |--------------------------------------------------------------------------
    |
    | Los archivos se guardan en un disco privado y solo se entregan mediante
    | la ruta autorizada attachments.download.
    |
    */

    'attachments' => [
        'disk' => 'local',
        'max_kb' => 10240,
        'allowed_mimes' => [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ],
    ],
];
