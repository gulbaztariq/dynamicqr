@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
{{-- These pages are seen by a member of the public standing in front of a
     product, so they stay calm, branded and free of dashboard chrome. --}}
<body class="grid min-h-full place-items-center bg-slate-50 px-4 py-12 font-sans">
    <main class="w-full max-w-md text-center">
        {{ $slot }}

        <p class="mt-10 text-xs text-slate-400">
            Powered by {{ config('app.name') }}
        </p>
    </main>
</body>
</html>
