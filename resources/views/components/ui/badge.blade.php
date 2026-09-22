@props(['classes' => 'bg-slate-100 text-slate-700 ring-slate-600/20', 'icon' => null])

<span {{ $attributes->merge(['class' => 'badge '.$classes]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="h-3 w-3" />
    @endif
    {{ $slot }}
</span>
