@props([
    'chartKey' => '',
    'series' => [],
    'options' => [],
])

<div
    wire:ignore
    wire:key="apex-chart-{{ $chartKey }}"
    x-data='{
        chart: null,
        init() {
            this.chart = new ApexCharts(this.$el, @json(array_merge($options, ['series' => $series])));
            this.chart.render();
        },
        destroy() {
            if (this.chart) {
                this.chart.destroy();
            }
        },
    }'
    x-init="init()"
    {{ $attributes->merge(['class' => 'w-full']) }}
></div>
