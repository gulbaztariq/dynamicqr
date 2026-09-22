@props(['qrCode', 'showCode' => true])

{{-- The permanent printed link, with one-tap copy. --}}
<div class="flex flex-wrap items-center gap-2" x-data="copyable(@js($qrCode->short_url))">
    @if ($showCode)
        <span class="code-chip">{{ $qrCode->code }}</span>
    @endif

    <a href="{{ $qrCode->short_url }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600">
        <span class="max-w-[16rem] truncate">{{ \Illuminate\Support\Str::after($qrCode->short_url, '://') }}</span>
        <x-icon name="external" class="h-3.5 w-3.5 shrink-0" />
    </a>

    <button type="button" @click="copy()"
            class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            :aria-label="copied ? 'Link copied' : 'Copy short link'">
        <x-icon name="copy" class="h-3.5 w-3.5" x-show="!copied" />
        <x-icon name="check" class="h-3.5 w-3.5 text-state-good" x-show="copied" x-cloak />
        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
    </button>
</div>
