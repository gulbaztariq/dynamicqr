<x-layouts.dashboard title="QR codes">
    <x-ui.page-header title="QR codes"
        :description="number_format($counts['all']).' total · '.number_format($counts['unassigned']).' still in the unassigned pool'">
        <x-slot:actions>
            {{-- Exports follow the filters currently applied to the table. --}}
            <x-ui.export-menu :route="route('admin.qr-codes.export')" :params="request()->query()"
                              label="Export URLs">
                <x-slot:extra>
                    <x-ui.export-menu-item :href="route('admin.qr-codes.download', request()->query())"
                        icon="qr" description="Printable artwork for this selection">
                        QR images (PNG ZIP)
                    </x-ui.export-menu-item>
                    <x-ui.export-menu-item :href="route('admin.qr-codes.download', request()->query() + ['format' => 'svg'])"
                        icon="qr" description="Vector artwork for large printing">
                        QR images (SVG ZIP)
                    </x-ui.export-menu-item>
                </x-slot:extra>
            </x-ui.export-menu>

            <a href="{{ route('admin.qr-codes.create') }}" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" />
                Single code
            </a>
            <a href="{{ route('admin.batches.create') }}" class="btn-secondary">
                More options
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Generate in one go: type a number, optionally pick who gets them. --}}
    <form method="POST" action="{{ route('admin.batches.store') }}"
          class="card card-pad ring-brand-600/10">
        @csrf

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <div class="lg:w-56">
                <label for="quick_quantity" class="label">How many QR codes?</label>
                <input id="quick_quantity" name="quantity" type="number" inputmode="numeric"
                       min="1" max="{{ config('qr.max_batch_quantity') }}"
                       value="{{ old('quantity', 10) }}" required
                       placeholder="e.g. 100"
                       class="input mt-1.5 text-lg font-semibold @error('quantity') border-red-400 @enderror">
            </div>

            <div class="lg:w-64">
                <label for="quick_user" class="label">
                    Assign to <span class="font-normal text-slate-400">(optional)</span>
                </label>
                <select id="quick_user" name="user_id" class="input mt-1.5">
                    <option value="">Keep as unassigned stock</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->name }}{{ $customer->company ? ' — '.$customer->company : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="lg:w-48">
                <label for="quick_prefix" class="label">
                    Label prefix <span class="font-normal text-slate-400">(optional)</span>
                </label>
                <input id="quick_prefix" name="label_prefix" type="text" placeholder="Standee"
                       class="input mt-1.5">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Generate
                </button>
            </div>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Codes are created instantly with permanent links. You can set their destinations,
            reassign them or download the artwork at any time afterwards.
        </p>
    </form>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.qr-codes.index') }}" class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Search code, label or destination" class="input pl-9" aria-label="Search">
        </div>

        <select name="status" class="input lg:w-44" aria-label="Status">
            <option value="">Any status</option>
            <option value="unassigned" @selected(($filters['status'] ?? '') === 'unassigned')>Unassigned ({{ $counts['unassigned'] }})</option>
            <option value="assigned" @selected(($filters['status'] ?? '') === 'assigned')>Assigned</option>
            <option value="live" @selected(($filters['status'] ?? '') === 'live')>Live</option>
            <option value="unconfigured" @selected(($filters['status'] ?? '') === 'unconfigured')>No destination ({{ $counts['unconfigured'] }})</option>
            <option value="paused" @selected(($filters['status'] ?? '') === 'paused')>Paused ({{ $counts['paused'] }})</option>
        </select>

        <select name="user_id" class="input lg:w-52" aria-label="Customer">
            <option value="">Any customer</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $customer->id)>
                    {{ $customer->name }}{{ $customer->company ? ' — '.$customer->company : '' }}
                </option>
            @endforeach
        </select>

        <select name="batch_id" class="input lg:w-52" aria-label="Batch">
            <option value="">Any batch</option>
            @foreach ($batches as $batch)
                <option value="{{ $batch->id }}" @selected((string) ($filters['batch_id'] ?? '') === (string) $batch->id)>
                    {{ $batch->name }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="btn-primary">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('admin.qr-codes.index') }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    {{-- Table with bulk selection. This is the "assign 5 of these 100" workflow. --}}
    <div x-data="bulkSelect">
        <form method="POST" action="{{ route('admin.qr-codes.bulk') }}" @submit="confirmSubmit($event)">
            @csrf

            {{-- Sticky action bar, only present once something is selected. --}}
            <div x-show="count > 0" x-cloak
                 class="sticky top-16 z-20 mb-3 flex flex-wrap items-center gap-3 rounded-xl bg-slate-900 p-3 text-white shadow-lift">
                <span class="px-1 text-sm font-medium">
                    <span x-text="count"></span> selected
                </span>

                <select name="action" x-model="action" required
                        class="rounded-lg border-0 bg-white/10 py-1.5 text-sm text-white focus:ring-2 focus:ring-brand-400">
                    <option value="" class="text-slate-900">Choose an action…</option>
                    <option value="assign" class="text-slate-900">Assign to customer</option>
                    <option value="unassign" class="text-slate-900">Return to unassigned pool</option>
                    <option value="activate" class="text-slate-900">Resume</option>
                    <option value="deactivate" class="text-slate-900">Pause</option>
                    @if (auth()->user()->isSuperAdmin())
                        <option value="delete" class="text-slate-900">Delete</option>
                    @endif
                </select>

                <select name="user_id" x-show="action === 'assign'" x-cloak
                        class="rounded-lg border-0 bg-white/10 py-1.5 text-sm text-white focus:ring-2 focus:ring-brand-400">
                    <option value="" class="text-slate-900">Choose customer…</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" class="text-slate-900">
                            {{ $customer->name }}{{ $customer->company ? ' — '.$customer->company : '' }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn bg-brand-500 text-white hover:bg-brand-400" :disabled="!action">
                    Apply
                </button>

                <button type="button" @click="clear()" class="btn text-slate-300 hover:text-white">Cancel</button>
            </div>

            <x-ui.card :padded="false">
                @if ($qrCodes->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="w-12 px-4 py-3">
                                        <input type="checkbox" @change="toggleAll($event)" :checked="allSelected"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                               aria-label="Select all on this page">
                                    </th>
                                    <th class="th">Code</th>
                                    <th class="th">Destination</th>
                                    <th class="th">Customer</th>
                                    <th class="th">Status</th>
                                    <th class="th text-right">Scans</th>
                                    <th class="th"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white" x-ref="rows">
                                @foreach ($qrCodes as $qrCode)
                                    <tr class="hover:bg-slate-50" :class="selected.includes('{{ $qrCode->id }}') && 'bg-brand-50/50'">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="ids[]" value="{{ $qrCode->id }}"
                                                   data-row-id="{{ $qrCode->id }}" x-model="selected"
                                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                   aria-label="Select {{ $qrCode->code }}">
                                        </td>

                                        <td class="td">
                                            <div class="flex items-center gap-2">
                                                <span class="code-chip">{{ $qrCode->code }}</span>
                                                @if ($qrCode->label)
                                                    <span class="max-w-[10rem] truncate text-slate-500">{{ $qrCode->label }}</span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="td max-w-xs">
                                            @if ($qrCode->target_url)
                                                <span class="block truncate text-slate-600">{{ $qrCode->target_url }}</span>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>

                                        <td class="td">
                                            @if ($qrCode->owner)
                                                <a href="{{ route('admin.users.show', $qrCode->owner) }}"
                                                   class="font-medium text-slate-900 hover:text-brand-600">
                                                    {{ $qrCode->owner->name }}
                                                </a>
                                            @else
                                                <span class="text-slate-400">Unassigned</span>
                                            @endif
                                        </td>

                                        <td class="td"><x-ui.qr-status :qrCode="$qrCode" /></td>

                                        <td class="td tabular text-right font-semibold">{{ number_format($qrCode->scan_count) }}</td>

                                        <td class="td text-right">
                                            <a href="{{ route('admin.qr-codes.show', $qrCode) }}"
                                               class="font-medium text-brand-600 hover:text-brand-700">Manage</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-900/5 p-4">
                        {{ $qrCodes->links() }}
                    </div>
                @else
                    <x-ui.empty icon="qr"
                        :title="array_filter($filters) ? 'No QR codes match those filters' : 'No QR codes yet'"
                        description="Generate a batch to create printable codes you can assign to customers later.">
                        <x-slot:actions>
                            <a href="{{ route('admin.batches.create') }}" class="btn-primary">Generate a batch</a>
                        </x-slot:actions>
                    </x-ui.empty>
                @endif
            </x-ui.card>
        </form>
    </div>
</x-layouts.dashboard>
