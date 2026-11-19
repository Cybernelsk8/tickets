<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card>
            <flux:text>{{ __('Total de tickets') }}</flux:text>
            <flux:heading size="2xl">{{ $totalTickets }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Tickets abiertos') }}</flux:text>
            <flux:heading size="2xl">{{ $openTickets }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Tareas terminadas') }}</flux:text>
            <flux:heading size="2xl">{{ $completedTasks }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Tareas en proceso') }}</flux:text>
            <flux:heading size="2xl">{{ $inProgressTasks }}</flux:heading>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Indicadores') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Las gráficas con ApexCharts se incorporarán en la Fase 6.') }}
        </flux:text>
    </flux:card>
</div>
