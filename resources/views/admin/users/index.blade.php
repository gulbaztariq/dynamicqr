<x-layouts.dashboard title="Customers">
    <x-ui.page-header title="Customers" description="Everyone with a dashboard login.">
        <x-slot:actions>
            <a href="{{ route('admin.users.export') }}" class="btn-secondary">
                <x-icon name="download" class="h-4 w-4" /> CSV
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Add customer
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.users.index') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Search name, email, company or phone" class="input pl-9" aria-label="Search customers">
        </div>

        <select name="role" class="input sm:w-44" aria-label="Role">
            <option value="">Any role</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="input sm:w-40" aria-label="Status">
            <option value="">Any status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
        </select>

        <button type="submit" class="btn-primary">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('admin.users.index') }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <x-ui.card :padded="false">
        @if ($users->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Customer</th>
                            <th class="th">Role</th>
                            <th class="th text-right">QR codes</th>
                            <th class="th text-right">Live</th>
                            <th class="th text-right">Scans</th>
                            <th class="th">Last login</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($users as $user)
                            <tr class="hover:bg-slate-50">
                                <td class="td">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                            {{ $user->initials() }}
                                        </span>
                                        <span class="min-w-0">
                                            <a href="{{ route('admin.users.show', $user) }}"
                                               class="flex items-center gap-2 font-medium text-slate-900 hover:text-brand-600">
                                                {{ $user->name }}
                                                @unless ($user->is_active)
                                                    <x-ui.badge classes="bg-amber-100 text-amber-700 ring-amber-600/20" icon="pause">
                                                        Suspended
                                                    </x-ui.badge>
                                                @endunless
                                            </a>
                                            <span class="block truncate text-xs text-slate-500">
                                                {{ $user->email }}{{ $user->company ? ' · '.$user->company : '' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="td">
                                    <x-ui.badge :classes="$user->role->badgeClasses()">{{ $user->role->label() }}</x-ui.badge>
                                </td>
                                <td class="td tabular text-right font-semibold">{{ number_format($user->qr_codes_count) }}</td>
                                <td class="td tabular text-right">{{ number_format($user->live_qr_codes_count) }}</td>
                                <td class="td tabular text-right">{{ number_format((int) $user->total_scans) }}</td>
                                <td class="td text-slate-500">
                                    {{ $user->last_login_at?->diffForHumans(short: true) ?? 'Never' }}
                                </td>
                                <td class="td text-right">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="font-medium text-brand-600 hover:text-brand-700">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-900/5 p-4">{{ $users->links() }}</div>
        @else
            <x-ui.empty icon="users"
                :title="array_filter($filters) ? 'No customers match those filters' : 'No customers yet'"
                description="Add a customer when they buy hardware — you can create their QR codes in the same step.">
                <x-slot:actions>
                    <a href="{{ route('admin.users.create') }}" class="btn-primary">Add customer</a>
                </x-slot:actions>
            </x-ui.empty>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
