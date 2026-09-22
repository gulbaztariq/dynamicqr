<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrImageController;
use App\Http\Requests\Admin\BulkQrActionRequest;
use App\Http\Requests\QrCode\UpdateQrCodeRequest;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrCodeActivity;
use App\Models\User;
use App\Rules\SafeRedirectUrl;
use App\Services\AnalyticsService;
use App\Services\QrCodeService;
use App\Services\QrImageService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly AnalyticsService $analytics,
        private readonly QrImageService $images,
    ) {}

    public function index(Request $request): View
    {
        $qrCodes = $this->filtered($request)
            ->with(['owner:id,name,email', 'batch:id,name'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.qr-codes.index', [
            'qrCodes' => $qrCodes,
            'filters' => $request->only('q', 'status', 'user_id', 'batch_id'),
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
            'batches' => QrBatch::latest()->get(['id', 'name']),
            'counts' => [
                'all' => QrCode::count(),
                'unassigned' => QrCode::unassigned()->count(),
                'unconfigured' => QrCode::whereNull('target_url')->count(),
                'paused' => QrCode::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.qr-codes.create', [
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
            'selectedUserId' => $request->integer('user_id') ?: null,
        ]);
    }

    public function store(Request $request, QrCodeService $service): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'exists:users,id'],
            'target_url' => ['nullable', 'string', 'max:2000', new SafeRedirectUrl],
            'user_can_edit' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $qrCode = $service->createSingle([
            ...$data,
            'user_can_edit' => $request->boolean('user_can_edit', true),
        ], $request->user());

        return redirect()->route('admin.qr-codes.show', $qrCode)
            ->with('status', 'QR code '.$qrCode->code.' created.');
    }

    public function show(Request $request, QrCode $qrCode): View
    {
        $days = 30;
        $scans = $this->analytics->forQrCode($qrCode);

        return view('admin.qr-codes.show', [
            'qrCode' => $qrCode->load('owner', 'batch', 'creator'),
            'days' => $days,
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 6, $days),
            'referrers' => $this->analytics->breakdown(clone $scans, 'referrer_host', 5, $days),
            'recentScans' => (clone $scans)->latest('scanned_at')->limit(10)->get(),
            'history' => $qrCode->activities()->with('actor:id,name')->limit(25)->get(),
            'preview' => $this->images->dataUri($qrCode, ['size' => 420]),
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
        ]);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $data = $request->validated();

        $this->qrCodes->updateTargetUrl($qrCode, $data['target_url'] ?? null, $request->user());

        $qrCode->fill([
            'label' => $data['label'] ?? $qrCode->label,
            'notes' => $data['notes'] ?? $qrCode->notes,
            'user_can_edit' => $request->boolean('user_can_edit', $qrCode->user_can_edit),
        ]);

        if ($qrCode->isDirty()) {
            $qrCode->save();
            $qrCode->logActivity(QrCodeActivity::TYPE_UPDATED, 'Details updated', [], $request->user()->id);
        }

        return back()->with('status', 'QR code updated.');
    }

    /** Assign or release a single code from its detail page. */
    public function assign(Request $request, QrCode $qrCode): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        if (blank($data['user_id'] ?? null)) {
            $this->qrCodes->unassign([$qrCode], $request->user());

            return back()->with('status', 'QR code returned to the unassigned pool.');
        }

        $user = User::findOrFail($data['user_id']);
        $this->qrCodes->assign([$qrCode], $user, $request->user());

        return back()->with('status', 'QR code assigned to '.$user->name.'.');
    }

    /**
     * The bulk toolbar: "take these 5 codes out of the pool of 100 and give them
     * to this customer", plus pause/resume/delete over a selection.
     */
    public function bulk(BulkQrActionRequest $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        $qrCodes = QrCode::whereIn('id', $ids)->get();
        $actor = $request->user();

        $message = match ($request->input('action')) {
            'assign' => $this->bulkAssign($qrCodes, (int) $request->input('user_id'), $actor),
            'unassign' => $this->qrCodes->unassign($qrCodes, $actor).' QR code(s) returned to the pool.',
            'activate' => $this->qrCodes->setActive($qrCodes, true, $actor).' QR code(s) resumed.',
            'deactivate' => $this->qrCodes->setActive($qrCodes, false, $actor).' QR code(s) paused.',
            'delete' => $this->bulkDelete($qrCodes, $actor),
            default => 'Nothing to do.',
        };

        return back()->with('status', $message);
    }

    public function destroy(Request $request, QrCode $qrCode): RedirectResponse
    {
        $this->authorize('delete', $qrCode);

        $code = $qrCode->code;
        $qrCode->logActivity(QrCodeActivity::TYPE_DELETED, 'QR code deleted', [], $request->user()->id);
        $qrCode->delete();

        return redirect()->route('admin.qr-codes.index')
            ->with('status', 'QR code '.$code.' deleted. Anyone scanning it now sees a "not found" page.');
    }

    /** ZIP of the printable artwork for the current filter selection. */
    public function downloadZip(Request $request): BinaryFileResponse
    {
        $qrCodes = $this->filtered($request)->with('owner')->limit(2000)->get();

        abort_if($qrCodes->isEmpty(), 404, 'No QR codes match this selection.');

        return app(QrImageController::class)->streamZip(
            $qrCodes,
            'qr-codes-'.now()->format('Y-m-d').'.zip',
            ['format' => $request->string('format')->toString() === 'svg' ? 'svg' : 'png'],
        );
    }

    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)
            ->with('owner:id,name', 'batch:id,name')
            ->cursor()
            ->map(fn (QrCode $qrCode) => [
                $qrCode->code,
                $qrCode->label,
                $qrCode->short_url,
                $qrCode->target_url,
                $qrCode->owner?->name,
                $qrCode->batch?->name,
                $qrCode->statusLabel(),
                $qrCode->scan_count,
                $qrCode->unique_scan_count,
                $qrCode->last_scanned_at?->format('Y-m-d H:i'),
                $qrCode->created_at?->format('Y-m-d'),
            ]);

        return SpreadsheetExporter::download(
            $request->string('format')->toString(),
            'qr-codes-'.now()->format('Y-m-d'),
            ['Code', 'Label', 'Short link', 'Destination', 'Assigned to', 'Batch', 'Status', 'Scans', 'Unique scans', 'Last scan', 'Created'],
            $rows,
        );
    }

    /** Shared filter pipeline so the table, the CSV and the ZIP always agree. */
    private function filtered(Request $request)
    {
        return QrCode::query()
            ->search($request->string('q')->toString())
            ->status($request->string('status')->toString())
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('qr_batch_id', $request->integer('batch_id')));
    }

    private function bulkAssign($qrCodes, int $userId, User $actor): string
    {
        $user = User::findOrFail($userId);
        $count = $this->qrCodes->assign($qrCodes, $user, $actor);

        return $count.' QR code(s) assigned to '.$user->name.'. They are visible in that customer\'s dashboard now.';
    }

    private function bulkDelete($qrCodes, User $actor): string
    {
        if (! $actor->isSuperAdmin()) {
            return 'Only a super admin can delete QR codes.';
        }

        foreach ($qrCodes as $qrCode) {
            $qrCode->logActivity(QrCodeActivity::TYPE_DELETED, 'QR code deleted in bulk', [], $actor->id);
            $qrCode->delete();
        }

        return count($qrCodes).' QR code(s) deleted.';
    }
}
