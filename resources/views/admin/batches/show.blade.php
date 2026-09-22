<x-layouts.dashboard :title="$batch->name">
    <x-ui.page-header :title="$batch->name"
        :description="$batch->notes ?: 'Generated '.$batch->created_at->format('j M Y')"
        :back="route('admin.batches.index')" backLabel="All batches">
        <x-slot:actions>
            <a href="{{ route('admin.batches.print', $batch) }}" target="_blank" class="btn-secondary">
                <x-icon name="printer" class="h-4 w-4" /> Print sheet
            </a>
            <x-ui.export-menu :route="route('admin.qr-codes.export')"
                              :params="['batch_id' => $batch->id]" label="Export URLs">
                <x-slot:extra>
                    <x-ui.export-menu-item :href="route('admin.batches.download', $batch)"
                        icon="qr" description="Printable artwork for every code here">
                        QR images (PNG ZIP)
                    </x-ui.export-menu-item>
                    <x-ui.export-menu-item :href="route('admin.batches.download', [$batch, 'format' => 'svg'])"
                        icon="qr" description="Vector artwork for large printing">
                        QR images (SVG ZIP)
                    </x-ui.export-menu-item>
                </x-slot:extra>
            </x-ui.export-menu>
            <a href="{{ route('admin.qr-codes.index', ['batch_id' => $batch->id]) }}" class="btn-primary">
                Manage &amp; assign
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Codes in batch" :value="number_format($batch->qr_codes_count)" icon="qr" tone="brand" />
        <x-ui.stat label="Assigned" :value="number_format($batch->assigned_count)" icon="users" tone="green"
            :hint="number_format($batch->qr_codes_count - $batch->assigned_count).' still in stock'" />
        <x-ui.stat label="With a destination" :value="number_format($batch->configured_count)" icon="link" tone="blue" />
        <x-ui.stat label="Total scans" :value="number_format($totalScans)" icon="chart" tone="amber" />
    </div>

    <x-ui.card :padded="false">
        <form method="GET" action="{{ route('admin.batches.show', $batch) }}"
              class="flex flex-col gap-3 border-b border-slate-900/5 p-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search within this batch"
                       class="input pl-9" aria-label="Search batch">
            </div>
            <select name="status" class="input sm:w-48" aria-label="Status">
                <option value="">Any status</option>
                <option value="unassigned" @selected(($filters['status'] ?? '') === 'unassigned')>Unassigned</option>
                <option value="assigned" @selected(($filters['status'] ?? '') === 'assigned')>Assigned</option>
                <option value="live" @selected(($filters['status'] ?? '') === 'live')>Live</option>
                <option value="unconfigured" @selected(($filters['status'] ?? '') === 'unconfigured')>No destination</option>
            </select>
            <button type="submit" class="btn-primary">Filter</button>
        </form>

        @if ($qrCodes->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 sm:p-6 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($qrCodes as $qrCode)
                    <a href="{{ route('admin.qr-codes.show', $qrCode) }}"
                       class="group rounded-xl p-3 text-center ring-1 ring-slate-900/5 transition hover:shadow-card hover:ring-brand-200">
                        <img src="{{ route('qr.image', ['code' => $qrCode->code, 'format' => 'png']) }}?size=200&margin=8"
                             alt="QR code {{ $qrCode->code }}"
                             class="mx-auto h-24 w-24 rounded-lg" loading="lazy">

                        <p class="mt-2 font-mono text-xs font-bold tracking-wider text-slate-900">{{ $qrCode->code }}</p>

                        @if ($qrCode->label)
                            <p class="truncate text-[11px] text-slate-500">{{ $qrCode->label }}</p>
                        @endif

                        <p class="mt-1.5 truncate text-[11px]">
                            @if ($qrCode->owner)
                                <span class="font-medium text-slate-700">{{ $qrCode->owner->name }}</span>
                            @else
                                <span class="text-slate-400">Unassigned</span>
                            @endif
                        </p>

                        <div class="mt-2 flex items-center justify-center gap-1.5 text-[11px] text-slate-500">
                            <x-ui.qr-status :qrCode="$qrCode" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="border-t border-slate-900/5 p-4">{{ $qrCodes->links() }}</div>
        @else
            <x-ui.empty icon="qr" title="Nothing matches those filters" />
        @endif
    </x-ui.card>

    @if (auth()->user()->isSuperAdmin())
        <x-ui.card title="Danger zone">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-slate-600">
                    Deleting a batch removes its codes permanently. Only possible while none are assigned or scanned.
                </p>
                <form method="POST" action="{{ route('admin.batches.destroy', $batch) }}"
                      onsubmit="return confirm('Delete this batch and all of its QR codes?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger">
                        <x-icon name="trash" class="h-4 w-4" /> Delete batch
                    </button>
                </form>
            </div>
        </x-ui.card>
    @endif
</x-layouts.dashboard>
