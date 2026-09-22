<x-layouts.dashboard title="Activity log">
    <x-ui.page-header title="Activity log"
        description="Every change made to a printed code — who did it, when, and from where." />

    <form method="GET" action="{{ route('admin.activity') }}" class="card flex flex-wrap items-center gap-3 p-4">
        <label for="type" class="text-sm font-medium text-slate-700">Event</label>
        <select id="type" name="type" class="input max-w-xs">
            <option value="">All events</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('admin.activity') }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <x-ui.card :padded="false">
        @if ($activities->isNotEmpty())
            <ol class="divide-y divide-slate-100">
                @foreach ($activities as $entry)
                    <li class="flex gap-4 px-5 py-4 sm:px-6">
                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                            <x-icon :name="$entry->icon()" class="h-4 w-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-slate-900">{{ $entry->description }}</p>
                                @if ($entry->qrCode)
                                    <a href="{{ route('admin.qr-codes.show', $entry->qrCode) }}" class="code-chip hover:bg-slate-200">
                                        {{ $entry->qrCode->code }}
                                    </a>
                                @endif
                            </div>

                            @if ($entry->type === \App\Models\QrCodeActivity::TYPE_URL_CHANGED && $entry->meta)
                                <p class="mt-1 break-all text-xs text-slate-500">
                                    {{ $entry->meta['from'] ?? 'nothing' }}
                                    <span class="mx-1 text-slate-400">&rarr;</span>
                                    <span class="font-medium text-slate-700">{{ $entry->meta['to'] ?? 'nothing' }}</span>
                                </p>
                            @endif

                            <p class="mt-1 text-xs text-slate-400">
                                {{ $entry->created_at->format('j M Y, H:i') }}
                                · {{ $entry->actor?->name ?? 'System' }}
                                @if ($entry->qrCode?->owner)
                                    · owner: {{ $entry->qrCode->owner->name }}
                                @endif
                                @if ($entry->ip_address) · {{ $entry->ip_address }} @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="border-t border-slate-900/5 p-4">{{ $activities->links() }}</div>
        @else
            <x-ui.empty icon="clock" title="No activity recorded yet"
                description="Generating, assigning or repointing a QR code all show up here." />
        @endif
    </x-ui.card>
</x-layouts.dashboard>
