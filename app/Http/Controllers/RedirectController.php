<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\ScanRecorder;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The public endpoint that every printed QR code points at.
 *
 * Two rules matter here and are easy to get wrong:
 *
 *  - The redirect must be 302, never 301. A permanent redirect gets cached by
 *    the visitor's browser forever, and the customer's next destination change
 *    would silently not apply to anyone who already scanned.
 *  - The response must be no-store for the same reason.
 */
class RedirectController extends Controller
{
    public function __invoke(Request $request, string $code, ScanRecorder $recorder): RedirectResponse|Response
    {
        $qrCode = QrCode::query()
            ->where('code', ShortCodeGenerator::normalise($code))
            ->first();

        if (! $qrCode) {
            return response()->view('public.unknown', ['code' => $code], 404)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $recorder->record($qrCode, $request);

        if (! $qrCode->is_active) {
            return $this->landing('public.paused', $qrCode, 404);
        }

        if (blank($qrCode->target_url)) {
            return $this->landing('public.unconfigured', $qrCode, 200);
        }

        return redirect()->away($qrCode->target_url, 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer-when-downgrade');
    }

    private function landing(string $view, QrCode $qrCode, int $status): Response
    {
        return response()
            ->view($view, ['qrCode' => $qrCode], $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
