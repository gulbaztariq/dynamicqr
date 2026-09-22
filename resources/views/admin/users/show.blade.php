<x-layouts.dashboard :title="$user->name">
    <x-ui.page-header :title="$user->name"
        :description="collect([$user->company, $user->email, $user->phone])->filter()->implode(' · ')"
        :back="route('admin.users.index')" backLabel="All customers">
        <x-slot:actions>
            <a href="{{ route('admin.batches.create', ['user_id' => $user->id]) }}" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Generate codes for them
            </a>
            <a href="{{ route('admin.qr-codes.index', ['status' => 'unassigned']) }}" class="btn-secondary">
                <x-icon name="layers" class="h-4 w-4" /> Assign from pool ({{ number_format($unassignedCount) }})
            </a>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-primary">Edit</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        {{-- Account panel --}}
        <x-ui.card class="lg:col-span-1">
            <div class="flex flex-col items-center text-center">
                <span class="grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-lg font-semibold text-brand-700">
                    {{ $user->initials() }}
                </span>
                <p class="mt-3 font-semibold text-slate-900">{{ $user->name }}</p>
                <p class="text-sm text-slate-500">{{ $user->email }}</p>

                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <x-ui.badge :classes="$user->role->badgeClasses()">{{ $user->role->label() }}</x-ui.badge>
                    @if ($user->is_active)
                        <x-ui.badge classes="bg-emerald-100 text-emerald-700 ring-emerald-600/20" icon="check">Active</x-ui.badge>
                    @else
                        <x-ui.badge classes="bg-amber-100 text-amber-700 ring-amber-600/20" icon="pause">Suspended</x-ui.badge>
                    @endif
                </div>
            </div>

            <dl class="mt-6 space-y-3 border-t border-slate-900/5 pt-5 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">QR codes</dt>
                    <dd class="tabular font-semibold text-slate-900">{{ number_format($user->qr_codes_count) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Total scans</dt>
                    <dd class="tabular font-semibold text-slate-900">{{ number_format($summary['total']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Customer since</dt>
                    <dd class="text-slate-900">{{ $user->created_at->format('j M Y') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Last login</dt>
                    <dd class="text-slate-900">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
            </dl>

            @if ($user->notes)
                <div class="mt-5 rounded-lg bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $user->notes }}</p>
                </div>
            @endif

            <div class="mt-5 space-y-2 border-t border-slate-900/5 pt-5">
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                    @csrf
                    <button type="submit" class="btn-secondary w-full"
                            onclick="return confirm('Generate a new password for {{ $user->name }}?')">
                        <x-icon name="key" class="h-4 w-4" /> Reset password
                    </button>
                </form>

                @if ($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">
                            <x-icon :name="$user->is_active ? 'pause' : 'play'" class="h-4 w-4" />
                            {{ $user->is_active ? 'Suspend account' : 'Reactivate account' }}
                        </button>
                    </form>
                @endif

                @can('delete', $user)
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                          onsubmit="return confirm('Delete {{ $user->name }}? Their QR codes return to the unassigned pool and keep working.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-danger w-full">
                            <x-icon name="trash" class="h-4 w-4" /> Delete customer
                        </button>
                    </form>
                @endcan
            </div>
        </x-ui.card>

        <div class="space-y-6 lg:col-span-3">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.stat label="Scans, last {{ $days }} days" :value="number_format($summary['period'])" icon="chart"
                    tone="brand" :change="$summary['change_percent']" />
                <x-ui.stat label="Scans today" :value="number_format($summary['today'])" icon="qr" tone="blue" />
                <x-ui.stat label="Unique visitors" :value="number_format($summary['unique'])" icon="users" tone="green"
                    hint="All time" />
            </div>

            <x-ui.chart-line :series="$series" title="Their scan activity"
                :subtitle="'Daily totals for the last '.$days.' days'" />

            <div class="grid gap-6 sm:grid-cols-2">
                <x-ui.chart-donut :items="$devices" title="Devices" />
                <x-ui.breakdown :items="$countries" title="Countries" icon="globe" />
            </div>
        </div>
    </div>

    <x-ui.card :title="'QR codes ('.number_format($user->qr_codes_count).')'" :padded="false">
        <x-slot:actions>
            <a href="{{ route('admin.qr-codes.index', ['user_id' => $user->id]) }}"
               class="text-sm font-medium text-brand-600 hover:text-brand-700">Manage all</a>
        </x-slot:actions>

        @if ($qrCodes->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Code</th>
                            <th class="th">Destination</th>
                            <th class="th">Status</th>
                            <th class="th text-right">Scans</th>
                            <th class="th">Last scan</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($qrCodes as $qrCode)
                            <tr class="hover:bg-slate-50">
                                <td class="td">
                                    <div class="flex items-center gap-2">
                                        <span class="code-chip">{{ $qrCode->code }}</span>
                                        <span class="max-w-[10rem] truncate text-slate-500">{{ $qrCode->label }}</span>
                                    </div>
                                </td>
                                <td class="td max-w-xs truncate text-slate-600">{{ $qrCode->target_url ?: '—' }}</td>
                                <td class="td"><x-ui.qr-status :qrCode="$qrCode" /></td>
                                <td class="td tabular text-right font-semibold">{{ number_format($qrCode->scan_count) }}</td>
                                <td class="td text-slate-500">{{ $qrCode->last_scanned_at?->diffForHumans(short: true) ?? 'Never' }}</td>
                                <td class="td text-right">
                                    <a href="{{ route('admin.qr-codes.show', $qrCode) }}"
                                       class="font-medium text-brand-600 hover:text-brand-700">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-900/5 p-4">{{ $qrCodes->links() }}</div>
        @else
            <x-ui.empty icon="qr" title="No QR codes assigned yet"
                description="Generate codes for this customer, or assign some from the unassigned pool.">
                <x-slot:actions>
                    <a href="{{ route('admin.batches.create', ['user_id' => $user->id]) }}" class="btn-primary">
                        Generate codes
                    </a>
                    <a href="{{ route('admin.qr-codes.index', ['status' => 'unassigned']) }}" class="btn-secondary">
                        Assign from pool
                    </a>
                </x-slot:actions>
            </x-ui.empty>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
