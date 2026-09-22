@props(['hours', 'title' => 'Busiest times of day', 'subtitle' => null])

@php
    $total = array_sum($hours['values']);
    $peakIndex = $total > 0 ? array_search(max($hours['values']), $hours['values'], true) : null;
@endphp

<x-ui.card :title="$title" :subtitle="$subtitle">
    <x-slot:actions>
        @if ($peakIndex !== null)
            <span class="text-xs text-slate-500">
                Peak: <span class="font-semibold text-slate-900">{{ $hours['labels'][$peakIndex] }}</span>
            </span>
        @endif
    </x-slot:actions>

    @if ($total > 0)
        <div class="h-48">
            <canvas data-chart="bars"
                    data-chart-config='@json(['labels' => $hours['labels'], 'values' => $hours['values']])'></canvas>
        </div>
    @else
        <x-ui.empty icon="clock" title="No scans in this period"
            description="Hourly patterns appear once this QR code starts getting scanned." />
    @endif
</x-ui.card>
