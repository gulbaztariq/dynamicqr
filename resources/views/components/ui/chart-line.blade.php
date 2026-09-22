@props([
    'series',           // App\Services\AnalyticsService::timeSeries() output
    'title' => 'Scans over time',
    'subtitle' => null,
    'height' => 'h-72',
])

@php
    $datasets = [
        ['label' => 'Total scans', 'data' => $series['scans']],
        ['label' => 'Unique visitors', 'data' => $series['unique']],
    ];

    $config = ['labels' => $series['labels'], 'datasets' => $datasets];

    // Series totals double as the direct labels the legend needs, so identity is
    // never carried by colour alone.
    $totals = [array_sum($series['scans']), array_sum($series['unique'])];
    $swatches = ['#2a78d6', '#eb6834'];
    $hasData = $totals[0] > 0;
@endphp

<x-ui.card :title="$title" :subtitle="$subtitle">
    <x-slot:actions>
        {{-- Legend: always present for two series. --}}
        <div class="flex flex-wrap items-center gap-4">
            @foreach ($datasets as $i => $set)
                <span class="flex items-center gap-1.5 text-xs">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $swatches[$i] }}"></span>
                    <span class="text-slate-600">{{ $set['label'] }}</span>
                    <span class="tabular font-semibold text-slate-900">{{ number_format($totals[$i]) }}</span>
                </span>
            @endforeach
        </div>
    </x-slot:actions>

    @if ($hasData)
        <div class="{{ $height }}">
            <canvas data-chart="line" data-chart-config='@json($config)'></canvas>
        </div>

        {{-- Table view: the non-visual path to the same numbers. --}}
        <details class="group mt-4">
            <summary class="cursor-pointer text-xs font-medium text-slate-500 hover:text-slate-700">
                View as table
            </summary>
            <div class="mt-3 max-h-64 overflow-auto rounded-lg ring-1 ring-slate-900/5">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="th">Date</th>
                            <th class="th text-right">Total scans</th>
                            <th class="th text-right">Unique visitors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($series['dates'] as $i => $date)
                            <tr>
                                <td class="td">{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}</td>
                                <td class="td tabular text-right">{{ number_format($series['scans'][$i]) }}</td>
                                <td class="td tabular text-right">{{ number_format($series['unique'][$i]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @else
        <x-ui.empty icon="chart" title="No scans yet"
            description="Once people start scanning these QR codes, the daily trend appears here." />
    @endif
</x-ui.card>
