<?php

namespace App\Services;

use App\Models\QrCode as QrCodeModel;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;

/**
 * Renders the printable QR image for a code.
 *
 * The image always encodes the *short* URL, never the destination — that is the
 * whole point of a dynamic QR: the printed artwork stays valid forever while the
 * destination behind it changes.
 */
class QrImageService
{
    /** @return array{body: string, mime: string, extension: string} */
    public function render(QrCodeModel $qrCode, array $overrides = []): array
    {
        $design = array_merge($this->defaults(), $qrCode->design ?? [], array_filter($overrides, fn ($v) => $v !== null));

        return $this->build($qrCode->short_url, $design);
    }

    /** @return array{body: string, mime: string, extension: string} */
    public function build(string $data, array $design = []): array
    {
        $design = array_merge($this->defaults(), $design);
        $format = in_array($design['format'] ?? 'png', ['png', 'svg'], true) ? $design['format'] : 'png';

        $result = (new Builder(
            writer: $this->writer($format),
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->errorCorrection($design['error_correction'] ?? 'high'),
            size: $this->clampSize((int) $design['size']),
            margin: max(0, min(100, (int) $design['margin'])),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $this->color($design['foreground'], new Color(15, 23, 42)),
            backgroundColor: $this->color($design['background'], new Color(255, 255, 255)),
        ))->build();

        return [
            'body' => $result->getString(),
            'mime' => $result->getMimeType(),
            'extension' => $format,
        ];
    }

    /** Data URI for inline previews in the dashboard, so no extra HTTP request is needed. */
    public function dataUri(QrCodeModel $qrCode, array $overrides = []): string
    {
        $image = $this->render($qrCode, $overrides);

        return 'data:'.$image['mime'].';base64,'.base64_encode($image['body']);
    }

    public function defaults(): array
    {
        return [
            'size' => (int) config('qr.design.size', 512),
            'margin' => (int) config('qr.design.margin', 16),
            'foreground' => (string) config('qr.design.foreground', '#0f172a'),
            'background' => (string) config('qr.design.background', '#ffffff'),
            'error_correction' => (string) config('qr.design.error_correction', 'high'),
            'format' => 'png',
        ];
    }

    private function writer(string $format): WriterInterface
    {
        return $format === 'svg' ? new SvgWriter : new PngWriter;
    }

    private function clampSize(int $size): int
    {
        return max(64, min((int) config('qr.design.max_size', 2000), $size));
    }

    private function errorCorrection(string $level): ErrorCorrectionLevel
    {
        return match (strtolower($level)) {
            'low' => ErrorCorrectionLevel::Low,
            'medium' => ErrorCorrectionLevel::Medium,
            'quartile' => ErrorCorrectionLevel::Quartile,
            default => ErrorCorrectionLevel::High,
        };
    }

    private function color(mixed $hex, Color $fallback): Color
    {
        if (! is_string($hex)) {
            return $fallback;
        }

        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return new Color(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    /** Filename used for downloads and inside batch ZIPs. */
    public function filename(QrCodeModel $qrCode, string $extension): string
    {
        $label = $qrCode->label ? '-'.str($qrCode->label)->slug() : '';

        return 'qr-'.$qrCode->code.$label.'.'.$extension;
    }
}
