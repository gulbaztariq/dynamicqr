<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $batch->name }} — print sheet</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm; }
        @media print {
            body { background: #fff; }
            .qr-cell { break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans">
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-3">
        <div>
            <h1 class="text-sm font-semibold text-slate-900">{{ $batch->name }}</h1>
            <p class="text-xs text-slate-500">{{ $qrCodes->count() }} codes · {{ $columns }} per row</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach ([2, 3, 4, 5, 6] as $option)
                <a href="{{ route('admin.batches.print', [$batch, 'columns' => $option]) }}"
                   @class([
                       'rounded-md px-2.5 py-1 text-xs font-semibold',
                       'bg-slate-900 text-white' => $columns === $option,
                       'bg-slate-100 text-slate-600 hover:bg-slate-200' => $columns !== $option,
                   ])>{{ $option }} up</a>
            @endforeach
            <button onclick="window.print()"
                    class="ml-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                Print
            </button>
        </div>
    </div>

    <div class="mx-auto max-w-5xl bg-white p-8 shadow-sm print:max-w-none print:p-0 print:shadow-none">
        <div class="grid gap-6" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));">
            @foreach ($qrCodes as $qrCode)
                <div class="qr-cell flex flex-col items-center rounded-lg border border-slate-200 p-3 text-center">
                    <img src="{{ $images[$qrCode->id] }}" alt="QR code {{ $qrCode->code }}" class="w-full max-w-[150px]">
                    <p class="mt-2 font-mono text-xs font-bold tracking-widest text-slate-900">{{ $qrCode->code }}</p>
                    @if ($qrCode->label)
                        <p class="truncate text-[10px] text-slate-500">{{ $qrCode->label }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
