<?php

namespace App;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Testing = 'testing';
    case Done = 'done';
    case Blocked = 'blocked';

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Tareas',
            self::Assigned => 'Asignado',
            self::InProgress => 'En proceso',
            self::Testing => 'Pruebas',
            self::Done => 'Terminado',
            self::Blocked => 'Bloqueado',
        };
    }

    /**
     * Columna del tablero Kanban donde se muestra este estado.
     */
    public function kanbanColumn(): string
    {
        return match ($this) {
            self::Todo => 'todo',
            self::Assigned => 'assigned',
            self::InProgress, self::Testing, self::Blocked => 'in_progress',
            self::Done => 'done',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Done;
    }

    /**
     * Determine whether the task may move directly to the given status.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::Todo => [self::Assigned, self::InProgress, self::Blocked],
            self::Assigned => [self::Todo, self::InProgress, self::Blocked],
            self::InProgress => [self::Todo, self::Assigned, self::Testing, self::Blocked],
            self::Testing => [self::InProgress, self::Done, self::Blocked],
            self::Blocked => [self::Todo, self::Assigned, self::InProgress],
            self::Done => [self::Testing, self::InProgress],
        };
    }
}
