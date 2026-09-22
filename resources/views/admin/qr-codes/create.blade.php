<x-layouts.dashboard title="New QR code">
    <x-ui.page-header title="Create a single QR code"
        description="For one-off replacements. To make many at once, generate a batch instead."
        :back="route('admin.qr-codes.index')" backLabel="All QR codes">
        <x-slot:actions>
            <a href="{{ route('admin.batches.create') }}" class="btn-secondary">Generate a batch instead</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.qr-codes.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="label" class="label">Label <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="label" name="label" type="text" value="{{ old('label') }}"
                       placeholder="e.g. Replacement standee for Cafe Aroma" class="input mt-1.5">
            </div>

            <div>
                <label for="user_id" class="label">Assign to customer <span class="font-normal text-slate-400">(optional)</span></label>
                <select id="user_id" name="user_id" class="input mt-1.5">
                    <option value="">— Leave in the unassigned pool —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('user_id', $selectedUserId) == $customer->id)>
                            {{ $customer->name }}{{ $customer->company ? ' ('.$customer->company.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="target_url" class="label">Destination <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="target_url" name="target_url" type="text" value="{{ old('target_url') }}"
                       placeholder="https://g.page/r/your-review-link" class="input mt-1.5">
                <p class="mt-1.5 text-xs text-slate-500">
                    Leave blank and the customer sets it themselves. The printed code works either way.
                </p>
            </div>

            <label class="flex items-start gap-3 rounded-lg bg-slate-50 p-3">
                <input type="checkbox" name="user_can_edit" value="1" @checked(old('user_can_edit', true))
                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm">
                    <span class="font-medium text-slate-900">Customer can change the destination</span>
                    <span class="mt-0.5 block text-slate-500">Untick to keep control with administrators only.</span>
                </span>
            </label>

            <div>
                <label for="notes" class="label">Internal notes</label>
                <textarea id="notes" name="notes" rows="2" class="input mt-1.5">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-2 border-t border-slate-900/5 pt-4">
                <button type="submit" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Create QR code
                </button>
                <a href="{{ route('admin.qr-codes.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
