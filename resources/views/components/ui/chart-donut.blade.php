@props([
    'items',                    // Collection of ['label' =>, 'count' =>, 'percent' =>]
    'title' => 'Devices',
    'subtitle' => null,
    'emptyText' => 'No scans in this period yet.',
])

@php
    // Capped at three coloured slots plus a neutral "Other": past three, adjacent
    // hues stop being reliably distinguishable in a pie/donut layout.
    $items = collect($items)->values();
    $head = $items->take(3);
    $tail = $items->slice(3);

    $slices = $head->map(fn ($item) => ['label' => $item['label'], 'count' => $item['count']])->values();

    if ($tail->isNotEmpty()) {
        $slices->push(['label' => 'Other', 'count' => $tail->sum('count')]);
    }

    $total = max(1, $slices->sum('count'));
    $swatches = ['#2a78d6', '#eb6834', '#1baf7a', '#c3c2b7'];
    $hasData = $items->sum('count') > 0;
@endphp

<x-ui.card :title="$title" :subtitle="$subtitle">
    @if ($hasData)
        <div class="flex flex-col items-center gap-5">
            <div class="relative h-36 w-36 shrink-0">
                <canvas data-chart="donut"
                        data-chart-config='@json(['labels' => $slices->pluck('label'), 'values' => $slices->pluck('count')])'></canvas>
                <div class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="text-center">
                        <p class="text-xl font-semibold text-slate-900">{{ number_format($total) }}</p>
                        <p class="text-[11px] text-slate-500">scans</p>
                    </div>
                </div>
            </div>

            {{-- Legend with direct labels: required relief for the lighter slots. --}}
            <ul class="w-full min-w-0 space-y-2">
                @foreach ($slices as $i => $slice)
                    <li class="flex items-center gap-2.5 text-sm">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $swatches[$i] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-slate-700">{{ $slice['label'] }}</span>
                        <span class="tabular shrink-0 font-semibold text-slate-900">{{ number_format($slice['count']) }}</span>
                        <span class="tabular w-14 shrink-0 text-right text-xs text-slate-500">
                            {{ number_format($slice['count'] / $total * 100, 1) }}%
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <x-ui.empty icon="chart" title="Nothing to show" :description="$emptyText" />
    @endif
</x-ui.card>
