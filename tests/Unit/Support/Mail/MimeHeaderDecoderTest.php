<?php

use App\Support\Mail\MimeHeaderDecoder;

it('decodes adjacent quoted printable words into one readable subject', function () {
    $encoded = '=?utf-8?Q?Convotaria_e_Informaci=C3=B3n_sobre_Programas_acad?= =?utf-8?Q?emicos_UNIVERSIDAD_MARIANO_GALVEZ_DE_GUATEMALA?=';

    expect(MimeHeaderDecoder::decode($encoded))
        ->toBe('Convotaria e Información sobre Programas academicos UNIVERSIDAD MARIANO GALVEZ DE GUATEMALA');
});

it('decodes base64 encoded words', function () {
    expect(MimeHeaderDecoder::decode('=?UTF-8?B?QWNjZXNvIGFsIHNpc3RlbWE?='))
        ->toBe('Acceso al sistema');
});

it('keeps unencoded text and punctuation intact', function () {
    $plain = 'TKT-0001 — Cierre de Recepción 2026';

    expect(MimeHeaderDecoder::decode($plain))->toBe($plain);
});

it('does not change a value that was already decoded', function () {
    $decoded = 'Cierre de Recepción de bienes muebles inservibles';

    expect(MimeHeaderDecoder::decode($decoded))->toBe($decoded);
});

it('returns null when the header is empty', function () {
    expect(MimeHeaderDecoder::decode(null))->toBeNull()
        ->and(MimeHeaderDecoder::decode(''))->toBeNull();
});
