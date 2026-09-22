<x-layouts.public title="QR code not found">
    <div class="card card-pad">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
            <x-icon name="search" class="h-7 w-7" />
        </span>

        <h1 class="mt-5 text-xl font-semibold text-slate-900">This QR code isn't recognised</h1>

        <p class="mt-2 text-sm text-slate-600">
            The code may have been mistyped, or it is no longer in use.
        </p>

        @if ($code)
            <p class="mt-4 text-xs text-slate-500">
                Code scanned: <span class="code-chip">{{ \Illuminate\Support\Str::limit($code, 32) }}</span>
            </p>
        @endif
    </div>
</x-layouts.public>
