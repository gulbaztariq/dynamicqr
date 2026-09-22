{{-- Session feedback. Every message pairs an icon with text so meaning never rests on colour. --}}

@if (session('status'))
    <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20">
        <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-state-good" />
        <p>{{ session('status') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="flex items-start gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-inset ring-red-600/20">
        <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-state-critical" />
        <p>{{ session('error') }}</p>
    </div>
@endif

@if (session('generated_password'))
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/30"
         x-data="copyable(@js(session('generated_password')))">
        <x-icon name="key" class="h-5 w-5 shrink-0 text-amber-600" />
        <p class="flex-1">
            Temporary password — copy it now, it is not shown again:
            <span class="code-chip ml-1 bg-white">{{ session('generated_password') }}</span>
        </p>
        <button type="button" class="btn-secondary" @click="copy()">
            <x-icon name="copy" class="h-4 w-4" />
            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-inset ring-red-600/20">
        <div class="flex items-center gap-2 font-semibold">
            <x-icon name="alert" class="h-5 w-5 text-state-critical" />
            Please fix the following:
        </div>
        <ul class="mt-2 list-inside list-disc space-y-1 pl-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
