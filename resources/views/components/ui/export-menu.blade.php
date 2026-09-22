@props([
    'route',              // Base export URL; format is appended as a query param.
    'params' => [],       // Current filters, so the export matches what's on screen.
    'label' => 'Export',
    'excelLabel' => 'Excel (.xlsx)',
    'csvLabel' => 'CSV (.csv)',
])

@php
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    $link = fn (string $format) => $route.'?'.http_build_query($params + ['format' => $format]);
@endphp

<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" class="btn-secondary" :aria-expanded="open" aria-haspopup="true">
        <x-icon name="download" class="h-4 w-4" />
        {{ $label }}
        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         x-transition.origin.top.right
         class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-xl bg-white py-1 shadow-lift ring-1 ring-slate-900/5">

        <a href="{{ $link('xlsx') }}" @click="open = false"
           class="flex items-start gap-3 px-3 py-2.5 text-sm hover:bg-slate-50">
            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-700">
                <x-icon name="chart" class="h-4 w-4" />
            </span>
            <span>
                <span class="block font-medium text-slate-900">{{ $excelLabel }}</span>
                <span class="block text-xs text-slate-500">Opens straight in Excel or Sheets</span>
            </span>
        </a>

        <a href="{{ $link('csv') }}" @click="open = false"
           class="flex items-start gap-3 px-3 py-2.5 text-sm hover:bg-slate-50">
            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-600">
                <x-icon name="link" class="h-4 w-4" />
            </span>
            <span>
                <span class="block font-medium text-slate-900">{{ $csvLabel }}</span>
                <span class="block text-xs text-slate-500">Plain text, for any other tool</span>
            </span>
        </a>

        {{-- Extra download options (QR artwork, for example). --}}
        @isset($extra)
            <div class="my-1 border-t border-slate-900/5"></div>
            {{ $extra }}
        @endisset
    </div>
</div>
