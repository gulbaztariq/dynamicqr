<x-layouts.dashboard title="Admin overview">
    <x-ui.page-header title="Overview" description="Stock, customers and scan performance across the whole system.">
        <x-slot:actions>
            <x-ui.range-picker :days="$days" :route="route('admin.dashboard')" />
            <a href="{{ route('admin.batches.create') }}" class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Generate QR codes
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Scans, last {{ $days }} days" :value="number_format($summary['period'])" icon="chart" tone="brand"
            :change="$summary['change_percent']" :hint="'vs previous '.$days.' days'" />

        <x-ui.stat label="Scans today" :value="number_format($summary['today'])" icon="qr" tone="blue"
            :hint="number_format($summary['yesterday']).' yesterday'" />

        <x-ui.stat label="QR codes in stock" :value="number_format($inventory['unassigned'])" icon="layers" tone="amber"
            :hint="number_format($inventory['total']).' total · '.number_format($inventory['assigned']).' assigned'" />

        <x-ui.stat label="Active customers" :value="number_format($customers['active'])" icon="users" tone="green"
            :hint="$customers['new_this_month'].' new this month'" />
    </div>

    <x-ui.chart-line :series="$series" title="Scans across all customers"
        :subtitle="'Daily totals for the last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Inventory health: what needs doing, not just what exists. --}}
        <x-ui.card title="Inventory" subtitle="Where your printed stock stands">
            <dl class="space-y-4">
                @php
                    $rows = [
                        ['label' => 'Unassigned (ready to sell)', 'value' => $inventory['unassigned'], 'link' => route('admin.qr-codes.index', ['status' => 'unassigned'])],
                        ['label' => 'Assigned to customers', 'value' => $inventory['assigned'], 'link' => route('admin.qr-codes.index', ['status' => 'assigned'])],
                        ['label' => 'Live and redirecting', 'value' => $inventory['live'], 'link' => route('admin.qr-codes.index', ['status' => 'live'])],
                        ['label' => 'Awaiting a destination', 'value' => $inventory['unconfigured'], 'link' => route('admin.qr-codes.index', ['status' => 'unconfigured'])],
                        ['label' => 'Paused', 'value' => $inventory['paused'], 'link' => route('admin.qr-codes.index', ['status' => 'paused'])],
                    ];
                    $max = max(1, $inventory['total']);
                @endphp

                @foreach ($rows as $row)
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <a href="{{ $row['link'] }}" class="text-sm text-slate-700 hover:text-brand-600">{{ $row['label'] }}</a>
                            <span class="tabular text-sm font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-series-1" style="width: {{ max(2, round($row['value'] / $max * 100)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.chart-donut :items="$devices" title="Devices" subtitle="Across all scans" />

        <x-ui.breakdown :items="$countries" title="Top countries" icon="globe" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- The follow-up list: customers holding codes that point nowhere. --}}
        <x-ui.card title="Customers needing follow-up" subtitle="Holding codes with no destination set" :padded="false">
            @if ($awaitingSetup->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($awaitingSetup as $customer)
                        <li class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                {{ $customer->initials() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('admin.users.show', $customer) }}"
                                   class="block truncate text-sm font-medium text-slate-900 hover:text-brand-600">
                                    {{ $customer->name }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">{{ $customer->company ?: $customer->email }}</span>
                            </span>
                            <x-ui.badge classes="bg-amber-100 text-amber-700 ring-amber-600/20" icon="alert">
                                {{ $customer->pending_count }} to set up
                            </x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="check" title="Everything is configured"
                    description="Every assigned QR code has a destination." />
            @endif
        </x-ui.card>

        <x-ui.card title="Top performing QR codes" :subtitle="'Last '.$days.' days'" :padded="false">
            @if ($topQrCodes->isNotEmpty() && $topQrCodes->sum('period_scans') > 0)
                <ul class="divide-y divide-slate-100">
                    @foreach ($topQrCodes as $qrCode)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <span class="code-chip shrink-0">{{ $qrCode->code }}</span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('admin.qr-codes.show', $qrCode) }}"
                                   class="block truncate text-sm font-medium text-slate-900 hover:text-brand-600">
                                    {{ $qrCode->label ?: 'Untitled' }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $qrCode->owner?->name ?? 'Unassigned' }}
                                </span>
                            </span>
                            <span class="tabular shrink-0 text-sm font-semibold text-slate-900">
                                {{ number_format($qrCode->period_scans) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty icon="chart" title="No scans in this period" />
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Recent activity" subtitle="Every change made to a printed code" :padded="false">
        <x-slot:actions>
            <a href="{{ route('admin.activity') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
        </x-slot:actions>

        @if ($recentActivity->isNotEmpty())
            <ul class="divide-y divide-slate-100">
                @foreach ($recentActivity as $entry)
                    <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                            <x-icon :name="$entry->icon()" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="text-slate-900">{{ $entry->description }}</span>
                            @if ($entry->qrCode)
                                <a href="{{ route('admin.qr-codes.show', $entry->qrCode) }}"
                                   class="ml-1 font-mono text-xs font-semibold text-brand-600 hover:underline">
                                    {{ $entry->qrCode->code }}
                                </a>
                            @endif
                        </span>
                        <span class="shrink-0 text-xs text-slate-500">
                            {{ $entry->actor?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans(short: true) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <x-ui.empty icon="clock" title="No activity yet" />
        @endif
    </x-ui.card>
</x-layouts.dashboard>
