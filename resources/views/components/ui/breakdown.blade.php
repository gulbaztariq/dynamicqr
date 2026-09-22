@props([
    'items',                // Collection of ['label' =>, 'count' =>, 'percent' =>]
    'title',
    'subtitle' => null,
    'icon' => 'chart',
    'emptyText' => 'No data for this period yet.',
])

@php $items = collect($items); @endphp

{{--
    A ranked list rather than a chart: for "top countries / browsers / sources"
    the label is the point, and a bar behind each row carries magnitude without
    needing a second colour dimension. One hue, values always visible.
--}}
<x-ui.card :title="$title" :subtitle="$subtitle">
    @if ($items->sum('count') > 0)
        <ul class="space-y-3">
            @foreach ($items as $item)
                <li>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="truncate text-slate-700">{{ $item['label'] }}</span>
                        <span class="shrink-0 tabular">
                            <span class="font-semibold text-slate-900">{{ number_format($item['count']) }}</span>
                            <span class="ml-1 text-xs text-slate-500">{{ $item['percent'] }}%</span>
                        </span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-series-1" style="width: {{ max(2, $item['percent']) }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-ui.empty :icon="$icon" title="Nothing yet" :description="$emptyText" />
    @endif
</x-ui.card>
