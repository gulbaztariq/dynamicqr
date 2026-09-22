<x-layouts.dashboard title="Add customer">
    <x-ui.page-header title="Add a customer"
        description="Create their login, and optionally their QR codes, in one step."
        :back="route('admin.users.index')" backLabel="All customers" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required
                           class="input mt-1.5 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="label">Email <span class="font-normal text-slate-400">(their login)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                           class="input mt-1.5 @error('email') border-red-400 @enderror">
                    @error('email') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company" class="label">Business name</label>
                    <input id="company" name="company" type="text" value="{{ old('company') }}"
                           placeholder="e.g. Cafe Aroma" class="input mt-1.5">
                </div>

                <div>
                    <label for="phone" class="label">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="input mt-1.5">
                </div>

                <div>
                    <label for="role" class="label">Role</label>
                    <select id="role" name="role" class="input mt-1.5">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', 'user') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="password" class="label">Password <span class="font-normal text-slate-400">(leave blank to auto-generate)</span></label>
                    <input id="password" name="password" type="text" value="{{ old('password') }}"
                           placeholder="{{ $generatedPassword }}" autocomplete="new-password" class="input mt-1.5">
                    @error('password') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-slate-500">A generated password is shown once after saving.</p>
                </div>
            </div>

            {{-- The "they bought N standees" shortcut. --}}
            <div class="rounded-xl bg-brand-50/60 p-4 ring-1 ring-inset ring-brand-600/10">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <x-icon name="qr" class="h-4 w-4 text-brand-600" />
                    Create their QR codes now
                </h3>
                <p class="mt-1 text-xs text-slate-600">
                    Bought 10 standees? Enter 10 here and their codes are created and assigned immediately.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="qr_quantity" class="label">How many QR codes?</label>
                        <input id="qr_quantity" name="qr_quantity" type="number" min="0"
                               max="{{ config('qr.max_batch_quantity') }}" value="{{ old('qr_quantity', 0) }}"
                               class="input mt-1.5">
                        @error('qr_quantity') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="qr_label_prefix" class="label">Label prefix</label>
                        <input id="qr_label_prefix" name="qr_label_prefix" type="text"
                               value="{{ old('qr_label_prefix', 'Standee') }}" class="input mt-1.5">
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Prefer to hand out pre-printed stock instead? Leave this at 0 and assign codes from the pool later.
                </p>
            </div>

            <div>
                <label for="notes" class="label">Internal notes</label>
                <textarea id="notes" name="notes" rows="2" class="input mt-1.5"
                          placeholder="Order reference, delivery details, anything useful">{{ old('notes') }}</textarea>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Account is active and can sign in</span>
            </label>

            <div class="flex items-center gap-2 border-t border-slate-900/5 pt-4">
                <button type="submit" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Create customer
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
