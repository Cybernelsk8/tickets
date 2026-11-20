<?php

namespace App;

enum TaskBoardColumn: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * Etiqueta legible de la columna en el tablero.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Por hacer',
            self::InProgress => 'En proceso',
            self::Done => 'Terminado',
        };
    }
}
