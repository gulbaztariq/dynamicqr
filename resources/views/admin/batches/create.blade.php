<x-layouts.dashboard title="Generate QR codes">
    <x-ui.page-header title="Generate QR codes"
        description="Create a run of printable codes — either straight onto a customer, or as stock you assign later."
        :back="route('admin.batches.index')" backLabel="All batches" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.batches.store') }}" class="space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="quantity" class="label">How many codes?</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="{{ $maxQuantity }}"
                               value="{{ old('quantity', 10) }}" required
                               class="input mt-1.5 @error('quantity') border-red-400 @enderror">
                        @error('quantity')
                            <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-slate-500">Up to {{ number_format($maxQuantity) }} in one run.</p>
                    </div>

                    <div>
                        <label for="name" class="label">Batch name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                               placeholder="e.g. October print run" class="input mt-1.5">
                    </div>
                </div>

                <div>
                    <label for="user_id" class="label">Assign to a customer now?</label>
                    <select id="user_id" name="user_id" class="input mt-1.5">
                        <option value="">— No, keep them as unassigned stock —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('user_id', $selectedUserId) == $customer->id)>
                                {{ $customer->name }}{{ $customer->company ? ' ('.$customer->company.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Pick a customer for the "somebody just bought 10 standees" case. Leave blank to print stock
                        first and hand out slices of it later.
                    </p>
                </div>

                <div>
                    <label for="label_prefix" class="label">Label prefix <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="label_prefix" name="label_prefix" type="text" value="{{ old('label_prefix') }}"
                           placeholder="Standee" class="input mt-1.5">
                    <p class="mt-1.5 text-xs text-slate-500">
                        Codes get numbered labels — "Standee 1", "Standee 2", and so on.
                    </p>
                </div>

                <div>
                    <label for="target_url" class="label">Default destination <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="target_url" name="target_url" type="text" value="{{ old('target_url') }}"
                           placeholder="https://your-landing-page.com" class="input mt-1.5">
                    <p class="mt-1.5 text-xs text-slate-500">
                        Every code in the run starts here. Leave blank and each shows a holding page until set.
                    </p>
                </div>

                <label class="flex items-start gap-3 rounded-lg bg-slate-50 p-3">
                    <input type="checkbox" name="user_can_edit" value="1" @checked(old('user_can_edit', true))
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm">
                        <span class="font-medium text-slate-900">Customers can change these destinations</span>
                        <span class="mt-0.5 block text-slate-500">Untick for codes only administrators may repoint.</span>
                    </span>
                </label>

                <div>
                    <label for="notes" class="label">Internal notes</label>
                    <textarea id="notes" name="notes" rows="2" class="input mt-1.5"
                              placeholder="Supplier, print order number, anything useful later">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-2 border-t border-slate-900/5 pt-4">
                    <button type="submit" class="btn-primary">
                        <x-icon name="plus" class="h-4 w-4" /> Generate codes
                    </button>
                    <a href="{{ route('admin.batches.index') }}" class="btn-ghost">Cancel</a>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="How this works">
            <ol class="space-y-4 text-sm text-slate-600">
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span>
                    <span>Each code gets a permanent short link like
                        <span class="code-chip">{{ url(config('qr.redirect_prefix').'/AB12CD3') }}</span>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span>
                    <span>Download the batch as a ZIP of PNG/SVG artwork, or print a ready-made sheet.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">3</span>
                    <span>Stick them onto standees or cards and ship them.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">4</span>
                    <span>Assign codes to a customer whenever you like — they appear in that customer's dashboard
                        straight away.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">5</span>
                    <span>The destination can change any number of times. <span class="font-medium text-slate-900">The
                        printed artwork never does.</span></span>
                </li>
            </ol>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
