@props(['href', 'icon', 'active' => false, 'badge' => null])

<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-white/10 text-white' => $active,
       'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
   ])
   @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" @class([
        'h-5 w-5 shrink-0',
        'text-brand-300' => $active,
        'text-slate-500 group-hover:text-slate-300' => ! $active,
    ]) />
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-brand-500/20 px-2 py-0.5 text-[11px] font-semibold text-brand-200">{{ $badge }}</span>
    @endif
</a>
