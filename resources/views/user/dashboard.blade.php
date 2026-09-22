<x-layouts.dashboard title="Overview">
    <x-ui.page-header
        title="Welcome back, {{ str(auth()->user()->name)->before(' ') }}"
        description="Everything your QR codes are doing, at a glance.">
        <x-slot:actions>
            <x-ui.range-picker :days="$days" :route="route('dashboard')" />
            <a href="{{ route('analytics') }}" class="btn-secondary">
                <x-icon name="chart" class="h-4 w-4" />
                Full analytics
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Codes still pointing nowhere are the one thing worth interrupting for. --}}
    @if ($needsSetup->isNotEmpty())
        <div class="rounded-xl bg-amber-50 p-5 ring-1 ring-inset ring-amber-600/20">
            <div class="flex items-start gap-3">
                <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-amber-900">
                        {{ $counts['unconfigured'] }}
                        {{ \Illuminate\Support\Str::plural('QR code', $counts['unconfigured']) }}
                        {{ $counts['unconfigured'] === 1 ? 'has' : 'have' }} no destination yet
                    </h2>
                    <p class="mt-0.5 text-sm text-amber-800">
                        Anyone scanning them sees a holding page. Add a link and they go live instantly — no reprint needed.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($needsSetup as $qrCode)
                            <a href="{{ route('qr-codes.show', $qrCode) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-600/30 hover:bg-amber-100">
                                <span class="font-mono font-semibold tracking-wider">{{ $qrCode->code }}</span>
                                @if ($qrCode->label)
                                    <span class="text-amber-700">· {{ $qrCode->label }}</span>
                                @endif
                            </a>
                        @endforeach
                        @if ($counts['unconfigured'] > $needsSetup->count())
                            <a href="{{ route('qr-codes.index', ['status' => 'unconfigured']) }}"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-amber-900 underline">
                                View all {{ $counts['unconfigured'] }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Headline numbers --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Scans today" :value="number_format($summary['today'])" icon="qr" tone="brand"
            :hint="$summary['yesterday'] > 0 ? number_format($summary['yesterday']).' yesterday' : 'No scans yesterday'" />

        <x-ui.stat label="Last {{ $days }} days" :value="number_format($summary['period'])" icon="chart" tone="blue"
            :change="$summary['change_percent']"
            :hint="'vs previous '.$days.' days'" />

        <x-ui.stat label="Unique visitors" :value="number_format($summary['unique'])" icon="users" tone="green"
            hint="All time, repeat scans excluded" />

        <x-ui.stat label="Live QR codes" :value="$counts['live'].' / '.$counts['total']" icon="link" tone="amber"
            :hint="$counts['paused'] > 0 ? $counts['paused'].' paused' : 'All active'" />
    </div>

    {{-- Trend --}}
    <x-ui.chart-line :series="$series"
        title="Scans over time"
        :subtitle="'Daily totals for the last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.chart-donut :items="$devices" title="Devices" subtitle="What people scan with" class="lg:col-span-1" />

        <x-ui.card title="Top performing QR codes" :subtitle="'Most scanned in the last '.$days.' days'" class="lg:col-span-2" :padded="false">
            @if ($topQrCodes->isNotEmpty() && $topQrCodes->sum('period_scans') > 0)
                <div class="divide-y divide-slate-100">
                    @foreach ($topQrCodes as $qrCode)
                        <a href="{{ route('qr-codes.show', $qrCode) }}"
                           class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50 sm:px-6">
                            <span class="code-chip shrink-0">{{ $qrCode->code }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-900">
                                    {{ $qrCode->label ?: 'Untitled QR code' }}
                                </span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $qrCode->target_url ?: 'No destination set' }}
                                </span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="tabular block text-sm font-semibold text-slate-900">
                                    {{ number_format($qrCode->period_scans) }}
                                </span>
                                <span class="block text-[11px] text-slate-500">scans</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="qr" title="No scans yet"
                    description="Your best performing codes will be ranked here once they start getting scanned.">
                    <x-slot:actions>
                        <a href="{{ route('qr-codes.index') }}" class="btn-primary">View my QR codes</a>
                    </x-slot:actions>
                </x-ui.empty>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.breakdown :items="$countries" title="Where scans come from" icon="globe"
            emptyText="Country data appears as soon as your codes are scanned." />

        <x-ui.card title="Recent scans" subtitle="The latest activity across your codes" :padded="false">
            @if ($recentScans->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentScans as $scan)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="text-lg" aria-hidden="true">{{ \App\Services\Countries::flag($scan->country_code) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-slate-900">
                                    {{ $scan->qrCode?->label ?: $scan->qrCode?->code }}
                                </span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ ucfirst($scan->device_type ?: 'Unknown') }} · {{ $scan->browser ?: 'Unknown' }}
                                    @if ($scan->country_name) · {{ $scan->country_name }} @endif
                                </span>
                            </span>
                            <time class="shrink-0 text-xs text-slate-500" datetime="{{ $scan->scanned_at?->toIso8601String() }}">
                                {{ $scan->scanned_at?->diffForHumans(short: true) }}
                            </time>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="clock" title="No scans recorded yet"
                    description="Scan one of your codes with a phone and it will show up here within seconds." />
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
