<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrImageController;
use App\Http\Requests\Admin\GenerateBatchRequest;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\User;
use App\Services\QrCodeService;
use App\Services\QrImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Batches are how stock gets made: either straight onto a customer, or as an
 * unassigned pool that gets handed out later.
 */
class BatchController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly QrImageService $images,
    ) {}

    public function index(): View
    {
        $batches = QrBatch::query()
            ->withCount([
                'qrCodes',
                'qrCodes as assigned_count' => fn ($q) => $q->whereNotNull('user_id'),
                'qrCodes as configured_count' => fn ($q) => $q->whereNotNull('target_url'),
            ])
            ->withSum('qrCodes as total_scans', 'scan_count')
            ->with('creator:id,name')
            ->latest()
            ->paginate(15);

        return view('admin.batches.index', ['batches' => $batches]);
    }

    public function create(Request $request): View
    {
        return view('admin.batches.create', [
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
            'selectedUserId' => $request->integer('user_id') ?: null,
            'maxQuantity' => (int) config('qr.max_batch_quantity'),
        ]);
    }

    public function store(GenerateBatchRequest $request): RedirectResponse
    {
        $batch = $this->qrCodes->generateBatch($request->validated(), $request->user());

        $assignedTo = $batch->qrCodes->first()?->owner?->name;

        return redirect()->route('admin.batches.show', $batch)->with(
            'status',
            $batch->quantity.' QR codes generated'.($assignedTo ? ' and assigned to '.$assignedTo : ' into the unassigned pool').'.'
        );
    }

    public function show(Request $request, QrBatch $batch): View
    {
        $qrCodes = $batch->qrCodes()
            ->with('owner:id,name')
            ->search($request->string('q')->toString())
            ->status($request->string('status')->toString())
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return view('admin.batches.show', [
            'batch' => $batch->loadCount([
                'qrCodes',
                'qrCodes as assigned_count' => fn ($q) => $q->whereNotNull('user_id'),
                'qrCodes as configured_count' => fn ($q) => $q->whereNotNull('target_url'),
            ]),
            'qrCodes' => $qrCodes,
            'filters' => $request->only('q', 'status'),
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
            'totalScans' => (int) $batch->qrCodes()->sum('scan_count'),
        ]);
    }

    /** Every code in the batch as a ZIP of PNGs plus a CSV manifest for the printer. */
    public function download(Request $request, QrBatch $batch): StreamedResponse
    {
        $qrCodes = $batch->qrCodes()->with('owner')->get();

        abort_if($qrCodes->isEmpty(), 404, 'This batch has no QR codes.');

        return app(QrImageController::class)->streamZip(
            $qrCodes,
            str($batch->name)->slug().'-qr-codes.zip',
            ['format' => $request->string('format')->toString() === 'svg' ? 'svg' : 'png'],
        );
    }

    /** A print-ready A4 grid, for sticking onto standees in-house. */
    public function printSheet(Request $request, QrBatch $batch): View
    {
        $qrCodes = $batch->qrCodes()->orderBy('id')->limit(500)->get();

        return view('admin.batches.print', [
            'batch' => $batch,
            'qrCodes' => $qrCodes,
            'images' => $qrCodes->mapWithKeys(fn (QrCode $qrCode) => [
                $qrCode->id => $this->images->dataUri($qrCode, ['size' => 300, 'margin' => 8]),
            ]),
            'columns' => max(2, min(6, (int) $request->integer('columns', 4))),
        ]);
    }

    public function destroy(Request $request, QrBatch $batch): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        // Only ever safe when nothing has been handed out or scanned.
        $inUse = $batch->qrCodes()
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhere('scan_count', '>', 0))
            ->exists();

        if ($inUse) {
            return back()->with('error', 'This batch has assigned or already-scanned codes, so it cannot be deleted. Unassign them first.');
        }

        $batch->qrCodes()->delete();
        $batch->delete();

        return redirect()->route('admin.batches.index')->with('status', 'Batch deleted.');
    }
}
