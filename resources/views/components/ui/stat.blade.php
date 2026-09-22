@props([
    'label',
    'value',
    'icon' => null,
    'change' => null,      // percentage vs the previous period, null when not comparable
    'hint' => null,
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];

    $rising = $change !== null && $change >= 0;
@endphp

<div {{ $attributes->merge(['class' => 'card card-pad']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>

    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($change !== null)
            {{-- Direction is carried by an arrow and the sign, never by colour alone. --}}
            <span @class([
                'inline-flex items-center gap-0.5 font-semibold',
                'text-state-good' => $rising,
                'text-state-critical' => ! $rising,
            ])>
                <x-icon :name="$rising ? 'arrow-up' : 'arrow-down'" class="h-3.5 w-3.5" />
                {{ $rising ? '+' : '' }}{{ $change }}%
            </span>
        @endif

        @if ($hint)
            <span class="text-slate-500">{{ $hint }}</span>
        @endif
    </div>
</div>
