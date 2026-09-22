<x-layouts.dashboard title="Batches">
    <x-ui.page-header title="Batches" description="Each print run of QR codes you have generated.">
        <x-slot:actions>
            <a href="{{ route('admin.batches.create') }}" class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Generate QR codes
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        @if ($batches->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Batch</th>
                            <th class="th text-right">Codes</th>
                            <th class="th text-right">Assigned</th>
                            <th class="th text-right">Configured</th>
                            <th class="th text-right">Scans</th>
                            <th class="th">Created</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($batches as $batch)
                            <tr class="hover:bg-slate-50">
                                <td class="td">
                                    <a href="{{ route('admin.batches.show', $batch) }}"
                                       class="font-medium text-slate-900 hover:text-brand-600">{{ $batch->name }}</a>
                                    @if ($batch->notes)
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $batch->notes }}</span>
                                    @endif
                                </td>
                                <td class="td tabular text-right font-semibold">{{ number_format($batch->qr_codes_count) }}</td>
                                <td class="td tabular text-right">{{ number_format($batch->assigned_count) }}</td>
                                <td class="td tabular text-right">{{ number_format($batch->configured_count) }}</td>
                                <td class="td tabular text-right">{{ number_format((int) $batch->total_scans) }}</td>
                                <td class="td text-slate-500">
                                    {{ $batch->created_at->format('j M Y') }}
                                    @if ($batch->creator)
                                        <span class="block text-xs">by {{ $batch->creator->name }}</span>
                                    @endif
                                </td>
                                <td class="td text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.batches.print', $batch) }}" target="_blank"
                                           class="text-slate-500 hover:text-brand-600" title="Print sheet">
                                            <x-icon name="printer" class="h-4 w-4" />
                                        </a>
                                        <a href="{{ route('admin.batches.download', $batch) }}"
                                           class="text-slate-500 hover:text-brand-600" title="Download ZIP">
                                            <x-icon name="download" class="h-4 w-4" />
                                        </a>
                                        <a href="{{ route('admin.batches.show', $batch) }}"
                                           class="font-medium text-brand-600 hover:text-brand-700">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-900/5 p-4">{{ $batches->links() }}</div>
        @else
            <x-ui.empty icon="layers" title="No batches yet"
                description="Generate your first run of QR codes to get started.">
                <x-slot:actions>
                    <a href="{{ route('admin.batches.create') }}" class="btn-primary">Generate QR codes</a>
                </x-slot:actions>
            </x-ui.empty>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
