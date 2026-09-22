@props(['href', 'icon' => 'download', 'description' => null])

<a href="{{ $href }}" @click="open = false"
   {{ $attributes->merge(['class' => 'flex items-start gap-3 px-3 py-2.5 text-sm hover:bg-slate-50']) }}>
    <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-600">
        <x-icon :name="$icon" class="h-4 w-4" />
    </span>
    <span>
        <span class="block font-medium text-slate-900">{{ $slot }}</span>
        @if ($description)
            <span class="block text-xs text-slate-500">{{ $description }}</span>
        @endif
    </span>
</a>
