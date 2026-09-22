<x-layouts.dashboard :title="$qrCode->label ?: $qrCode->code">
    <x-ui.page-header
        :title="$qrCode->label ?: 'QR code '.$qrCode->code"
        :description="'Created '.$qrCode->created_at->format('j M Y')"
        :back="route('qr-codes.index')" backLabel="All QR codes">
        <x-slot:actions>
            <x-ui.range-picker :days="$days" :route="route('qr-codes.show', $qrCode)" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Artwork + permanent link --}}
        <x-ui.card class="lg:col-span-1">
            <div class="flex flex-col items-center text-center">
                <img src="{{ $preview }}" alt="QR code {{ $qrCode->code }}"
                     class="h-48 w-48 rounded-xl ring-1 ring-slate-900/5">

                <p class="mt-4 font-mono text-lg font-bold tracking-[0.2em] text-slate-900">{{ $qrCode->code }}</p>

                <div class="mt-2 flex items-center gap-2">
                    <x-ui.qr-status :qrCode="$qrCode" />
                </div>

                <div class="mt-4 w-full rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Permanent link</p>
                    <div class="mt-1.5 flex justify-center">
                        <x-ui.short-link :qrCode="$qrCode" :showCode="false" />
                    </div>
                </div>

                <div class="mt-4 grid w-full grid-cols-2 gap-2">
                    <a href="{{ route('qr.download', ['code' => $qrCode->code, 'format' => 'png']) }}?size=1024"
                       class="btn-secondary">
                        <x-icon name="download" class="h-4 w-4" />
                        PNG
                    </a>
                    <a href="{{ route('qr.download', ['code' => $qrCode->code, 'format' => 'svg']) }}"
                       class="btn-secondary">
                        <x-icon name="download" class="h-4 w-4" />
                        SVG
                    </a>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Print this once. Changing the destination below never changes the code.
                </p>
            </div>
        </x-ui.card>

        {{-- The core action: repoint the code --}}
        <x-ui.card title="Where this QR code sends people"
                   subtitle="Update it as often as you like — it takes effect on the very next scan."
                   class="lg:col-span-2">
            @if ($canEdit)
                <form method="POST" action="{{ route('qr-codes.update', $qrCode) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="target_url" class="label">Destination link</label>
                        <input id="target_url" name="target_url" type="text"
                               value="{{ old('target_url', $qrCode->target_url) }}"
                               placeholder="https://g.page/r/your-google-review-link"
                               class="input mt-1.5 @error('target_url') border-red-400 @enderror"
                               autocomplete="url">
                        @error('target_url')
                            <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-slate-500">
                            Your Google review page, Instagram profile, WhatsApp link, menu, website — anything.
                        </p>
                    </div>

                    <div>
                        <label for="label" class="label">Name <span class="font-normal text-slate-400">(only you see this)</span></label>
                        <input id="label" name="label" type="text" value="{{ old('label', $qrCode->label) }}"
                               placeholder="e.g. Reception standee" class="input mt-1.5">
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-900/5 pt-4">
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" />
                            Save destination
                        </button>

                        <button type="submit" form="toggle-form" class="btn-secondary">
                            <x-icon :name="$qrCode->is_active ? 'pause' : 'play'" class="h-4 w-4" />
                            {{ $qrCode->is_active ? 'Pause this code' : 'Resume this code' }}
                        </button>
                    </div>
                </form>

                <form id="toggle-form" method="POST" action="{{ route('qr-codes.toggle', $qrCode) }}" class="hidden">
                    @csrf
                </form>
            @else
                <div class="rounded-lg bg-slate-50 p-4">
                    <div class="flex items-start gap-3">
                        <x-icon name="key" class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                        <div>
                            <p class="text-sm font-medium text-slate-900">This code is managed by the administrator</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                It currently points to
                                <span class="font-medium text-slate-900">{{ $qrCode->target_url ?: 'nowhere yet' }}</span>.
                                Contact support to have it changed.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-900/5 pt-5 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-slate-500">Total scans</dt>
                    <dd class="tabular mt-0.5 text-xl font-semibold text-slate-900">{{ number_format($qrCode->scan_count) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Unique visitors</dt>
                    <dd class="tabular mt-0.5 text-xl font-semibold text-slate-900">{{ number_format($qrCode->unique_scan_count) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Last {{ $days }} days</dt>
                    <dd class="tabular mt-0.5 text-xl font-semibold text-slate-900">{{ number_format($summary['period']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Last scan</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-900">
                        {{ $qrCode->last_scanned_at?->diffForHumans() ?? 'Never' }}
                    </dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    <x-ui.chart-line :series="$series" :subtitle="'Daily scans for the last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.chart-donut :items="$devices" title="Devices" />
        <x-ui.breakdown :items="$countries" title="Countries" icon="globe" />
        <x-ui.breakdown :items="$browsers" title="Browsers & apps" icon="chart" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.chart-hours :hours="$hours" :subtitle="'Based on the last '.$days.' days'" />
        <x-ui.breakdown :items="$referrers" title="How people arrived" icon="link"
            emptyText="Most QR scans are direct from a camera app, so this is often empty." />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Audit trail: proof of what this printed code pointed at, and when. --}}
        <x-ui.card title="Destination history" subtitle="Every change made to this code" :padded="false">
            @if ($history->isNotEmpty())
                <ol class="divide-y divide-slate-100">
                    @foreach ($history as $entry)
                        <li class="flex gap-3 px-5 py-3.5 sm:px-6">
                            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                                <x-icon :name="$entry->icon()" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-900">{{ $entry->description }}</p>
                                @if ($entry->type === \App\Models\QrCodeActivity::TYPE_URL_CHANGED && $entry->meta)
                                    <p class="mt-0.5 truncate text-xs text-slate-500">
                                        {{ $entry->meta['from'] ?? 'nothing' }}
                                        <span class="mx-1">&rarr;</span>
                                        <span class="text-slate-700">{{ $entry->meta['to'] ?? 'nothing' }}</span>
                                    </p>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $entry->created_at->format('j M Y, H:i') }}
                                    @if ($entry->actor) · {{ $entry->actor->name }} @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui.empty icon="clock" title="No changes yet" />
            @endif
        </x-ui.card>

        <x-ui.card title="Recent scans" :padded="false">
            @if ($recentScans->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentScans as $scan)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="text-lg" aria-hidden="true">{{ \App\Services\Countries::flag($scan->country_code) }}</span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block text-slate-900">
                                    {{ ucfirst($scan->device_type ?: 'Unknown') }} · {{ $scan->browser ?: 'Unknown' }}
                                </span>
                                <span class="block text-xs text-slate-500">
                                    {{ $scan->country_name ?: 'Location unknown' }}
                                    @if ($scan->is_unique) · first-time visitor @endif
                                </span>
                            </span>
                            <time class="shrink-0 text-xs text-slate-500">{{ $scan->scanned_at?->diffForHumans(short: true) }}</time>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="clock" title="No scans recorded yet" />
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
