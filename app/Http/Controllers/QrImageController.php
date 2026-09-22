<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\QrImageService;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Serves the printable artwork.
 *
 * These routes are public on purpose: a QR image only encodes a public short
 * link, and print shops, suppliers and customers all need to fetch it without
 * a login. Nothing about the destination or its analytics is exposed here.
 */
class QrImageController extends Controller
{
    public function __construct(private readonly QrImageService $images) {}

    /** Inline image: /qr/ABC1234.png?size=800&fg=%23000000 */
    public function show(Request $request, string $code, string $format = 'png'): Response
    {
        $qrCode = $this->resolve($code);
        $image = $this->images->render($qrCode, $this->overrides($request, $format));

        return response($image['body'], 200, [
            'Content-Type' => $image['mime'],
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="'.$this->images->filename($qrCode, $image['extension']).'"',
        ]);
    }

    /** Same image as an attachment, for the download buttons in the dashboard. */
    public function download(Request $request, string $code, string $format = 'png'): Response
    {
        $qrCode = $this->resolve($code);
        $image = $this->images->render($qrCode, $this->overrides($request, $format));

        return response($image['body'], 200, [
            'Content-Type' => $image['mime'],
            'Content-Disposition' => 'attachment; filename="'.$this->images->filename($qrCode, $image['extension']).'"',
        ]);
    }

    /**
     * Zip every code in a selection. Written to a temp file and streamed so a
     * 2000-code batch does not have to sit in memory.
     *
     * @param  Collection<int, QrCode>  $qrCodes
     */
    public function streamZip(Collection $qrCodes, string $filename, array $design = []): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'qrzip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        $format = $design['format'] ?? 'png';

        foreach ($qrCodes as $qrCode) {
            $image = $this->images->render($qrCode, $design);
            $zip->addFromString($this->images->filename($qrCode, $image['extension']), $image['body']);
        }

        // A manifest makes a printer's job much easier than filenames alone.
        $zip->addFromString('codes.csv', $this->manifest($qrCodes));
        $zip->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    private function manifest(Collection $qrCodes): string
    {
        $rows = [['Code', 'Label', 'Short link', 'Destination', 'Assigned to', 'Status']];

        foreach ($qrCodes as $qrCode) {
            $rows[] = [
                $qrCode->code,
                (string) $qrCode->label,
                $qrCode->short_url,
                (string) $qrCode->target_url,
                (string) $qrCode->owner?->name,
                $qrCode->statusLabel(),
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function resolve(string $code): QrCode
    {
        return QrCode::query()
            ->where('code', ShortCodeGenerator::normalise($code))
            ->firstOrFail();
    }

    /** Only whitelisted, clamped options are accepted from the query string. */
    private function overrides(Request $request, string $format): array
    {
        return array_filter([
            'format' => in_array($format, ['png', 'svg'], true) ? $format : 'png',
            'size' => $request->filled('size') ? (int) $request->integer('size') : null,
            'margin' => $request->filled('margin') ? (int) $request->integer('margin') : null,
            'foreground' => $request->filled('fg') ? (string) $request->string('fg') : null,
            'background' => $request->filled('bg') ? (string) $request->string('bg') : null,
        ], fn ($value) => $value !== null);
    }
}
