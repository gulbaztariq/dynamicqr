<?php

namespace App\Services;

use App\Models\QrCode;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Generates the short codes that get printed onto products.
 *
 * Codes are drawn from an unambiguous alphabet so somebody reading one off a
 * standee cannot confuse O with 0. Bulk generation checks the whole candidate
 * set against the database in one query rather than one round trip per code.
 */
class ShortCodeGenerator
{
    public function __construct(
        private readonly int $length = 0,
        private readonly string $alphabet = '',
    ) {}

    public function length(): int
    {
        return $this->length ?: (int) config('qr.code_length', 7);
    }

    public function alphabet(): string
    {
        return $this->alphabet ?: (string) config('qr.code_alphabet');
    }

    public function generate(): string
    {
        return $this->unique(1)->first();
    }

    /**
     * Produce $count codes that collide with nothing already stored.
     *
     * @return Collection<int, string>
     */
    public function unique(int $count): Collection
    {
        if ($count < 1) {
            return collect();
        }

        $codes = collect();
        $attempts = 0;

        while ($codes->count() < $count) {
            if (++$attempts > 20) {
                throw new RuntimeException(
                    'Unable to generate enough unique QR codes. Increase qr.code_length.'
                );
            }

            $needed = $count - $codes->count();

            // Over-generate a little so one round trip usually covers the shortfall.
            $candidates = collect()
                ->pad((int) ceil($needed * 1.2) + 5, null)
                ->map(fn () => $this->random())
                ->unique()
                ->reject(fn (string $code) => $codes->contains($code));

            $taken = QrCode::withTrashed()
                ->whereIn('code', $candidates->all())
                ->pluck('code')
                ->map(fn (string $code) => strtoupper($code))
                ->all();

            $codes = $codes
                ->merge($candidates->reject(fn (string $code) => in_array($code, $taken, true)))
                ->values()
                ->take($count);
        }

        return $codes->values();
    }

    public function random(): string
    {
        $alphabet = $this->alphabet();
        $max = strlen($alphabet) - 1;

        $code = '';
        for ($i = 0; $i < $this->length(); $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /** Normalise user input (typed links, scanned codes) to storage form. */
    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }
}
