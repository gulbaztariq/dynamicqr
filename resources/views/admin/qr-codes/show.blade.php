<x-layouts.dashboard :title="'QR '.$qrCode->code">
    <x-ui.page-header :title="$qrCode->label ?: 'QR code '.$qrCode->code"
        :description="'Created '.$qrCode->created_at->format('j M Y').($qrCode->batch ? ' · Batch: '.$qrCode->batch->name : '')"
        :back="route('admin.qr-codes.index')" backLabel="All QR codes" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-1">
            <div class="flex flex-col items-center text-center">
                <img src="{{ $preview }}" alt="QR code {{ $qrCode->code }}" class="h-44 w-44 rounded-xl ring-1 ring-slate-900/5">
                <p class="mt-4 font-mono text-lg font-bold tracking-[0.2em] text-slate-900">{{ $qrCode->code }}</p>
                <div class="mt-2"><x-ui.qr-status :qrCode="$qrCode" /></div>

                <div class="mt-4 w-full rounded-lg bg-slate-50 p-3">
                    <div class="flex justify-center"><x-ui.short-link :qrCode="$qrCode" :showCode="false" /></div>
                </div>

                <div class="mt-4 grid w-full grid-cols-2 gap-2">
                    <a href="{{ route('qr.download', ['code' => $qrCode->code, 'format' => 'png']) }}?size=1024" class="btn-secondary">
                        <x-icon name="download" class="h-4 w-4" /> PNG
                    </a>
                    <a href="{{ route('qr.download', ['code' => $qrCode->code, 'format' => 'svg']) }}" class="btn-secondary">
                        <x-icon name="download" class="h-4 w-4" /> SVG
                    </a>
                </div>
            </div>

            {{-- Assignment --}}
            <form method="POST" action="{{ route('admin.qr-codes.assign', $qrCode) }}" class="mt-6 border-t border-slate-900/5 pt-5">
                @csrf
                <label for="assign_user" class="label">Assigned to</label>
                <select id="assign_user" name="user_id" class="input mt-1.5">
                    <option value="">— Unassigned pool —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($qrCode->user_id === $customer->id)>
                            {{ $customer->name }}{{ $customer->company ? ' ('.$customer->company.')' : '' }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn-secondary mt-3 w-full">Update assignment</button>
                @if ($qrCode->assigned_at)
                    <p class="mt-2 text-xs text-slate-500">Assigned {{ $qrCode->assigned_at->diffForHumans() }}</p>
                @endif
            </form>
        </x-ui.card>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Destination" subtitle="Takes effect on the very next scan — no reprint needed.">
                <form method="POST" action="{{ route('admin.qr-codes.update', $qrCode) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="target_url" class="label">Destination link</label>
                        <input id="target_url" name="target_url" type="text"
                               value="{{ old('target_url', $qrCode->target_url) }}"
                               placeholder="https://g.page/r/your-review-link"
                               class="input mt-1.5 @error('target_url') border-red-400 @enderror">
                        @error('target_url')
                            <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="label" class="label">Label</label>
                            <input id="label" name="label" type="text" value="{{ old('label', $qrCode->label) }}" class="input mt-1.5">
                        </div>
                        <div>
                            <label for="notes" class="label">Internal notes</label>
                            <input id="notes" name="notes" type="text" value="{{ old('notes', $qrCode->notes) }}" class="input mt-1.5">
                        </div>
                    </div>

                    <label class="flex items-start gap-3 rounded-lg bg-slate-50 p-3">
                        <input type="checkbox" name="user_can_edit" value="1" @checked($qrCode->user_can_edit)
                               class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm">
                            <span class="font-medium text-slate-900">Customer can change this destination</span>
                            <span class="mt-0.5 block text-slate-500">
                                Untick to lock the code so only an administrator can repoint it.
                            </span>
                        </span>
                    </label>

                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-900/5 pt-4">
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Save changes
                        </button>

                        @if (auth()->user()->isSuperAdmin())
                            <button type="submit" form="delete-qr" class="btn-danger ml-auto"
                                    onclick="return confirm('Delete {{ $qrCode->code }}? Anyone scanning it will see a not-found page.')">
                                <x-icon name="trash" class="h-4 w-4" /> Delete
                            </button>
                        @endif
                    </div>
                </form>

                @if (auth()->user()->isSuperAdmin())
                    <form id="delete-qr" method="POST" action="{{ route('admin.qr-codes.destroy', $qrCode) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endif
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.stat label="Total scans" :value="number_format($qrCode->scan_count)" icon="qr" tone="brand" />
                <x-ui.stat label="Unique" :value="number_format($qrCode->unique_scan_count)" icon="users" tone="green" />
                <x-ui.stat label="Last {{ $days }} days" :value="number_format($summary['period'])" icon="chart" tone="blue"
                    :change="$summary['change_percent']" />
                <x-ui.stat label="Last scan" :value="$qrCode->last_scanned_at?->diffForHumans(short: true) ?? 'Never'"
                    icon="clock" tone="slate" />
            </div>
        </div>
    </div>

    <x-ui.chart-line :series="$series" :subtitle="'Daily scans for the last '.$days.' days'" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.chart-donut :items="$devices" title="Devices" />
        <x-ui.breakdown :items="$countries" title="Countries" icon="globe" />
        <x-ui.breakdown :items="$referrers" title="Traffic sources" icon="link"
            emptyText="Camera-app scans arrive with no referrer." />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Full audit trail" subtitle="Every change ever made to this code" :padded="false">
            @if ($history->isNotEmpty())
                <ol class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                    @foreach ($history as $entry)
                        <li class="flex gap-3 px-5 py-3 sm:px-6">
                            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                                <x-icon :name="$entry->icon()" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-900">{{ $entry->description }}</p>
                                @if ($entry->type === \App\Models\QrCodeActivity::TYPE_URL_CHANGED && $entry->meta)
                                    <p class="mt-0.5 truncate text-xs text-slate-500">
                                        {{ $entry->meta['from'] ?? 'nothing' }} &rarr;
                                        <span class="text-slate-700">{{ $entry->meta['to'] ?? 'nothing' }}</span>
                                    </p>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $entry->created_at->format('j M Y, H:i') }}
                                    @if ($entry->actor) · {{ $entry->actor->name }} @endif
                                    @if ($entry->ip_address) · {{ $entry->ip_address }} @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui.empty icon="clock" title="No activity yet" />
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
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $scan->country_name ?: 'Unknown location' }} · sent to {{ \Illuminate\Support\Str::limit($scan->target_url, 40) }}
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
