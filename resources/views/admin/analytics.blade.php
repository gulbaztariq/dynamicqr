<x-layouts.dashboard title="Analytics">
    <x-ui.page-header title="Analytics" description="Scan performance across every customer and code.">
        <x-slot:actions>
            <x-ui.range-picker :days="$days" :route="route('admin.analytics')" :params="['user_id' => request('user_id')]" />
            <a href="{{ route('admin.analytics.export', request()->only('range', 'user_id')) }}" class="btn-secondary">
                <x-icon name="download" class="h-4 w-4" /> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.analytics') }}" class="card flex flex-wrap items-center gap-3 p-4">
        <input type="hidden" name="range" value="{{ $days }}">
        <label for="user_id" class="text-sm font-medium text-slate-700">Customer</label>
        <select id="user_id" name="user_id" class="input max-w-xs" onchange="this.form.submit()">
            <option value="">All customers</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $customer->id)>
                    {{ $customer->name }}{{ $customer->company ? ' — '.$customer->company : '' }}
                </option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn-secondary">Apply</button></noscript>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Total scans" :value="number_format($summary['total'])" icon="qr" tone="brand" hint="All time" />
        <x-ui.stat label="Last {{ $days }} days" :value="number_format($summary['period'])" icon="chart" tone="blue"
            :change="$summary['change_percent']" :hint="'vs previous '.$days.' days'" />
        <x-ui.stat label="Unique visitors" :value="number_format($summary['unique'])" icon="users" tone="green" />
        <x-ui.stat label="Today" :value="number_format($summary['today'])" icon="clock" tone="amber"
            :hint="number_format($summary['last_7_days']).' in the last 7 days'" />
    </div>

    <x-ui.chart-line :series="$series" :subtitle="'Daily totals for the last '.$days.' days'" />

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

    <x-ui.chart-hours :hours="$hours" :subtitle="'Across all codes, last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Top QR codes" :subtitle="'By scans in the last '.$days.' days'" :padded="false">
            @if ($topQrCodes->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($topQrCodes as $qrCode)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="code-chip shrink-0">{{ $qrCode->code }}</span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('admin.qr-codes.show', $qrCode) }}"
                                   class="block truncate text-sm font-medium text-slate-900 hover:text-brand-600">
                                    {{ $qrCode->label ?: 'Untitled' }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">{{ $qrCode->owner?->name ?? 'Unassigned' }}</span>
                            </span>
                            <span class="tabular shrink-0 text-sm font-semibold text-slate-900">
                                {{ number_format($qrCode->period_scans) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="qr" title="No scans in this period" />
            @endif
        </x-ui.card>

        <x-ui.card title="Most active customers" :subtitle="'By scans in the last '.$days.' days'" :padded="false">
            @if ($topCustomers->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($topCustomers as $customer)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                {{ $customer->initials() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('admin.users.show', $customer) }}"
                                   class="block truncate text-sm font-medium text-slate-900 hover:text-brand-600">
                                    {{ $customer->name }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $customer->company ?: $customer->email }} · {{ $customer->qr_codes_count }} codes
                                </span>
                            </span>
                            <span class="tabular shrink-0 text-sm font-semibold text-slate-900">
                                {{ number_format($customer->period_scans) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="users" title="No customer activity yet" />
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
