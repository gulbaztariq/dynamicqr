<x-layouts.dashboard title="My QR codes">
    <x-ui.page-header title="My QR codes"
        description="Change where any code points. The printed artwork never changes.">
    </x-ui.page-header>

    {{-- Filters in one row above the table. --}}
    <x-ui.card :padded="false">
        <form method="GET" action="{{ route('qr-codes.index') }}"
              class="flex flex-col gap-3 border-b border-slate-900/5 p-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Search by code, name or destination"
                       class="input pl-9" aria-label="Search QR codes">
            </div>

            <select name="status" class="input sm:w-48" aria-label="Filter by status">
                <option value="">All statuses ({{ $counts['all'] }})</option>
                <option value="live" @selected(($filters['status'] ?? '') === 'live')>Live ({{ $counts['live'] }})</option>
                <option value="unconfigured" @selected(($filters['status'] ?? '') === 'unconfigured')>No destination ({{ $counts['unconfigured'] }})</option>
                <option value="paused" @selected(($filters['status'] ?? '') === 'paused')>Paused ({{ $counts['paused'] }})</option>
            </select>

            <button type="submit" class="btn-primary">Filter</button>

            @if (array_filter($filters))
                <a href="{{ route('qr-codes.index') }}" class="btn-ghost">Clear</a>
            @endif
        </form>

        @if ($qrCodes->isNotEmpty())
            <div class="divide-y divide-slate-100">
                @foreach ($qrCodes as $qrCode)
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-6 sm:px-6">
                        <img src="{{ route('qr.image', ['code' => $qrCode->code, 'format' => 'png']) }}?size=160&margin=8"
                             alt="QR code {{ $qrCode->code }}"
                             class="h-16 w-16 shrink-0 rounded-lg ring-1 ring-slate-900/5" loading="lazy">

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('qr-codes.show', $qrCode) }}"
                                   class="truncate text-sm font-semibold text-slate-900 hover:text-brand-600">
                                    {{ $qrCode->label ?: 'Untitled QR code' }}
                                </a>
                                <x-ui.qr-status :qrCode="$qrCode" />
                                @unless ($qrCode->user_can_edit)
                                    <x-ui.badge classes="bg-slate-100 text-slate-600 ring-slate-500/20" icon="key">
                                        Locked by admin
                                    </x-ui.badge>
                                @endunless
                            </div>

                            <div class="mt-1.5">
                                <x-ui.short-link :qrCode="$qrCode" />
                            </div>

                            <p class="mt-1 truncate text-xs text-slate-500">
                                @if ($qrCode->target_url)
                                    Goes to <span class="text-slate-700">{{ $qrCode->target_url }}</span>
                                @else
                                    <span class="font-medium text-amber-700">No destination set yet</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-6">
                            <div class="text-right">
                                <p class="tabular text-lg font-semibold text-slate-900">{{ number_format($qrCode->scan_count) }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ \Illuminate\Support\Str::plural('scan', $qrCode->scan_count) }}
                                </p>
                            </div>

                            <a href="{{ route('qr-codes.show', $qrCode) }}" class="btn-secondary">
                                {{ $qrCode->target_url ? 'Manage' : 'Set destination' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-900/5 p-4">
                {{ $qrCodes->links() }}
            </div>
        @else
            <x-ui.empty icon="qr"
                :title="array_filter($filters) ? 'No QR codes match those filters' : 'No QR codes assigned yet'"
                :description="array_filter($filters)
                    ? 'Try clearing the search or choosing a different status.'
                    : 'Once your order is processed, your QR codes appear here and you can point them anywhere you like.'">
                <x-slot:actions>
                    @if (array_filter($filters))
                        <a href="{{ route('qr-codes.index') }}" class="btn-secondary">Clear filters</a>
                    @endif
                </x-slot:actions>
            </x-ui.empty>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
