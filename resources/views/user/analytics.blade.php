<x-layouts.dashboard title="Analytics">
    <x-ui.page-header title="Analytics"
        :description="$selected ? 'Filtered to '.($selected->label ?: $selected->code) : 'Across all of your QR codes'">
        <x-slot:actions>
            <x-ui.range-picker :days="$days" :route="route('analytics')" :params="['qr' => request('qr')]" />
            <x-ui.export-menu :route="route('analytics.export')"
                              :params="request()->only('range', 'qr')"
                              label="Export scans" />
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Dimension filter, in the same row band as the range control. --}}
    @if ($qrCodes->count() > 1)
        <form method="GET" action="{{ route('analytics') }}" class="card flex flex-wrap items-center gap-3 p-4">
            <input type="hidden" name="range" value="{{ $days }}">
            <label for="qr" class="text-sm font-medium text-slate-700">Show</label>
            <select id="qr" name="qr" class="input max-w-xs" onchange="this.form.submit()">
                <option value="">All my QR codes ({{ $qrCodes->count() }})</option>
                @foreach ($qrCodes as $option)
                    <option value="{{ $option->uuid }}" @selected($selected?->id === $option->id)>
                        {{ $option->label ?: 'Untitled' }} — {{ $option->code }}
                    </option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn-secondary">Apply</button></noscript>
        </form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Total scans" :value="number_format($summary['total'])" icon="qr" tone="brand" hint="All time" />
        <x-ui.stat label="Last {{ $days }} days" :value="number_format($summary['period'])" icon="chart" tone="blue"
            :change="$summary['change_percent']" :hint="'vs previous '.$days.' days'" />
        <x-ui.stat label="Unique visitors" :value="number_format($summary['unique'])" icon="users" tone="green"
            hint="Repeat scans excluded" />
        <x-ui.stat label="Scans today" :value="number_format($summary['today'])" icon="clock" tone="amber"
            :hint="number_format($summary['last_7_days']).' in the last 7 days'" />
    </div>

    <x-ui.chart-line :series="$series" :subtitle="'Daily scans for the last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.chart-donut :items="$devices" title="Device types" />
        <x-ui.breakdown :items="$operatingSystems" title="Operating systems" icon="chart" />
        <x-ui.breakdown :items="$browsers" title="Browsers & in-app views" icon="chart" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.breakdown :items="$countries" title="Top countries" icon="globe" />
        <x-ui.breakdown :items="$referrers" title="Traffic sources" icon="link"
            emptyText="Camera-app scans arrive with no referrer, so this stays empty for most QR traffic." />
    </div>

    <x-ui.chart-hours :hours="$hours" :subtitle="'When your codes get scanned, last '.$days.' days'" />

    @unless ($selected)
        <x-ui.card title="All QR codes ranked" :subtitle="'By scans in the last '.$days.' days'" :padded="false">
            @if ($topQrCodes->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="th">QR code</th>
                                <th class="th">Destination</th>
                                <th class="th text-right">Last {{ $days }} days</th>
                                <th class="th text-right">All time</th>
                                <th class="th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($topQrCodes as $qrCode)
                                <tr class="hover:bg-slate-50">
                                    <td class="td">
                                        <div class="flex items-center gap-2">
                                            <span class="code-chip">{{ $qrCode->code }}</span>
                                            <span class="max-w-[12rem] truncate font-medium text-slate-900">
                                                {{ $qrCode->label ?: 'Untitled' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="td max-w-xs truncate text-slate-500">
                                        {{ $qrCode->target_url ?: '—' }}
                                    </td>
                                    <td class="td tabular text-right font-semibold text-slate-900">
                                        {{ number_format($qrCode->period_scans) }}
                                    </td>
                                    <td class="td tabular text-right">{{ number_format($qrCode->scan_count) }}</td>
                                    <td class="td text-right">
                                        <a href="{{ route('qr-codes.show', $qrCode) }}"
                                           class="text-sm font-medium text-brand-600 hover:text-brand-700">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui.empty icon="qr" title="No QR codes yet" />
            @endif
        </x-ui.card>
    @endunless
</x-layouts.dashboard>
