@props(['title' => null])

@php
    $user = auth()->user();
    $isAdmin = $user?->isStaff() ?? false;

    $nav = $isAdmin
        ? [
            ['route' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Overview', 'pattern' => 'admin'],
            ['route' => 'admin.qr-codes.index', 'icon' => 'qr', 'label' => 'QR codes', 'pattern' => 'admin/qr-codes*'],
            ['route' => 'admin.batches.index', 'icon' => 'layers', 'label' => 'Batches', 'pattern' => 'admin/batches*'],
            ['route' => 'admin.users.index', 'icon' => 'users', 'label' => 'Customers', 'pattern' => 'admin/users*'],
            ['route' => 'admin.analytics', 'icon' => 'chart', 'label' => 'Analytics', 'pattern' => 'admin/analytics*'],
            ['route' => 'admin.activity', 'icon' => 'clock', 'label' => 'Activity log', 'pattern' => 'admin/activity*'],
        ]
        : [
            ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Overview', 'pattern' => 'dashboard'],
            ['route' => 'qr-codes.index', 'icon' => 'qr', 'label' => 'My QR codes', 'pattern' => 'qr-codes*'],
            ['route' => 'analytics', 'icon' => 'chart', 'label' => 'Analytics', 'pattern' => 'analytics*'],
        ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-slate-50 font-sans">
<div x-data="{ mobileNav: false }" class="min-h-full">

    {{-- Mobile backdrop --}}
    <div x-show="mobileNav" x-cloak @click="mobileNav = false"
         class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside x-cloak
           :class="mobileNav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-900 transition-transform duration-200 lg:translate-x-0">

        <div class="flex h-16 items-center gap-3 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-500 text-white">
                <x-icon name="qr" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ config('app.name') }}</p>
                <p class="text-[11px] text-slate-400">{{ $isAdmin ? 'Admin console' : 'Customer dashboard' }}</p>
            </div>
            <button type="button" @click="mobileNav = false" class="rounded-md p-1 text-slate-400 hover:text-white lg:hidden">
                <x-icon name="close" class="h-5 w-5" />
                <span class="sr-only">Close menu</span>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as $item)
                <x-ui.nav-item :href="route($item['route'])" :icon="$item['icon']"
                               :active="request()->is($item['pattern'])">
                    {{ $item['label'] }}
                </x-ui.nav-item>
            @endforeach

            @if ($isAdmin)
                <div class="!mt-6 px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Quick actions
                </div>
                <x-ui.nav-item :href="route('admin.batches.create')" icon="plus">Generate QR codes</x-ui.nav-item>
                <x-ui.nav-item :href="route('admin.users.create')" icon="user">Add customer</x-ui.nav-item>
            @endif
        </nav>

        {{-- Account --}}
        <div class="border-t border-white/10 p-3" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-white/5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/20 text-sm font-semibold text-brand-200">
                    {{ $user?->initials() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-white">{{ $user?->name }}</span>
                    <span class="block truncate text-[11px] text-slate-400">{{ $user?->email }}</span>
                </span>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false" class="mt-1 space-y-1">
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5 hover:text-white">
                    <x-icon name="settings" class="h-4 w-4" />
                    Profile &amp; password
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5 hover:text-white">
                        <x-icon name="logout" class="h-4 w-4" />
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Content --}}
    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-900/5 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" @click="mobileNav = true" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden">
                <x-icon name="menu" class="h-5 w-5" />
                <span class="sr-only">Open menu</span>
            </button>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ $title ?? 'Dashboard' }}</p>
            </div>

            @isset($headerActions)
                <div class="flex items-center gap-2">{{ $headerActions }}</div>
            @endisset
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <x-ui.flash />
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
</body>
</html>
