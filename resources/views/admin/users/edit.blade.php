<x-layouts.dashboard :title="'Edit '.$user->name">
    <x-ui.page-header :title="'Edit '.$user->name" description="Update their details, role or password."
        :back="route('admin.users.show', $user)" backLabel="Back to customer" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
            @csrf @method('PATCH')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="input mt-1.5">
                    @error('name') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="input mt-1.5">
                    @error('email') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company" class="label">Business name</label>
                    <input id="company" name="company" type="text" value="{{ old('company', $user->company) }}" class="input mt-1.5">
                </div>

                <div>
                    <label for="phone" class="label">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="input mt-1.5">
                </div>

                <div>
                    <label for="role" class="label">Role</label>
                    <select id="role" name="role" class="input mt-1.5">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="password" class="label">New password <span class="font-normal text-slate-400">(leave blank to keep)</span></label>
                    <input id="password" name="password" type="text" autocomplete="new-password" class="input mt-1.5">
                    @error('password') <p class="mt-1.5 text-sm text-state-critical">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="notes" class="label">Internal notes</label>
                <textarea id="notes" name="notes" rows="3" class="input mt-1.5">{{ old('notes', $user->notes) }}</textarea>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Account is active and can sign in</span>
            </label>

            <div class="flex items-center gap-2 border-t border-slate-900/5 pt-4">
                <button type="submit" class="btn-primary"><x-icon name="check" class="h-4 w-4" /> Save changes</button>
                <a href="{{ route('admin.users.show', $user) }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
