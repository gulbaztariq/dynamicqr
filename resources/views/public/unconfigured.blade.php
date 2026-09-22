<x-layouts.public title="Almost ready">
    <div class="card card-pad">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600">
            <x-icon name="qr" class="h-7 w-7" />
        </span>

        <h1 class="mt-5 text-xl font-semibold text-slate-900">This QR code is almost ready</h1>

        <p class="mt-2 text-sm text-slate-600">
            It works, but a destination has not been set up yet. Please try again a little later.
        </p>

        <p class="mt-5 text-xs text-slate-500">
            Code <span class="code-chip">{{ $qrCode->code }}</span>
        </p>
    </div>
</x-layouts.public>
