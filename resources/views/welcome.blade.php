<x-layouts.public title="Dynamic QR codes">
    <div class="card card-pad">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-violet-500 text-white">
            <x-icon name="qr" class="h-7 w-7" />
        </span>

        <h1 class="mt-5 text-xl font-semibold text-slate-900">{{ config('app.name') }}</h1>

        <p class="mt-2 text-sm text-slate-600">
            Dynamic QR codes for NFC review products. Print once, change the destination whenever you like,
            and see exactly how each code performs.
        </p>

        <a href="{{ route('login') }}" class="btn-primary mt-6 w-full">Sign in to your dashboard</a>

        @if (config('app.allow_registration') && Route::has('register'))
            <a href="{{ route('register') }}" class="btn-secondary mt-2 w-full">Create an account</a>
        @else
            <p class="mt-4 text-xs text-slate-500">
                Accounts are created for you when you order. Contact us if you need access.
            </p>
        @endif
    </div>
</x-layouts.public>
