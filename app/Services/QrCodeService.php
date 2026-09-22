<?php

namespace App\Services;

use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrCodeActivity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The two ways stock gets created, and everything that can happen to it after.
 *
 *  1. Generate codes straight onto a customer ("this person bought 10 standees").
 *  2. Generate an unassigned pool up front and hand slices of it out later
 *     ("print 100, assign 5 of them to whoever walks in").
 *
 * Every state change is written to qr_code_activities so a printed code always
 * has an answer to "who pointed this somewhere else, and when?".
 */
class QrCodeService
{
    public function __construct(private readonly ShortCodeGenerator $codes) {}

    /**
     * Create a batch of codes, optionally pre-assigned to a customer.
     *
     * @param  array{name?: string|null, quantity: int, label_prefix?: string|null, user_id?: int|null,
     *               target_url?: string|null, notes?: string|null, user_can_edit?: bool, design?: array|null}  $data
     */
    public function generateBatch(array $data, ?User $actor = null): QrBatch
    {
        $quantity = max(1, min((int) $data['quantity'], (int) config('qr.max_batch_quantity', 2000)));
        $userId = $data['user_id'] ?? null;

        return DB::transaction(function () use ($data, $quantity, $userId, $actor) {
            $batch = QrBatch::create([
                'name' => $data['name'] ?: 'Batch '.now()->format('d M Y H:i'),
                'label_prefix' => $data['label_prefix'] ?? null,
                'quantity' => $quantity,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $codes = $this->codes->unique($quantity);
            $now = now();

            $rows = $codes->values()->map(fn (string $code, int $index) => [
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'qr_batch_id' => $batch->id,
                'user_id' => $userId,
                'label' => $this->buildLabel($data['label_prefix'] ?? null, $index + 1),
                'target_url' => $data['target_url'] ?? null,
                'is_active' => true,
                'user_can_edit' => $data['user_can_edit'] ?? true,
                'design' => isset($data['design']) ? json_encode($data['design']) : null,
                'scan_count' => 0,
                'unique_scan_count' => 0,
                'assigned_at' => $userId ? $now : null,
                'created_by' => $actor?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            // Chunked so a 2000-code batch does not blow the SQL placeholder limit.
            foreach (array_chunk($rows, 200) as $chunk) {
                QrCode::insert($chunk);
            }

            $created = QrCode::where('qr_batch_id', $batch->id)->get();
            $this->logBulk($created, QrCodeActivity::TYPE_CREATED, 'Generated in batch "'.$batch->name.'"', [
                'batch_id' => $batch->id,
            ], $actor);

            if ($userId) {
                $this->logBulk($created, QrCodeActivity::TYPE_ASSIGNED, 'Assigned on creation', [
                    'to_user_id' => $userId,
                ], $actor);
            }

            return $batch->load('qrCodes');
        });
    }

    /** Create a single code, used by the "add one QR" form. */
    public function createSingle(array $data, ?User $actor = null): QrCode
    {
        return DB::transaction(function () use ($data, $actor) {
            $qrCode = QrCode::create([
                'code' => $this->codes->generate(),
                'user_id' => $data['user_id'] ?? null,
                'label' => $data['label'] ?? null,
                'target_url' => $data['target_url'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'user_can_edit' => $data['user_can_edit'] ?? true,
                'notes' => $data['notes'] ?? null,
                'design' => $data['design'] ?? null,
                'assigned_at' => ($data['user_id'] ?? null) ? now() : null,
                'created_by' => $actor?->id,
            ]);

            $qrCode->logActivity(QrCodeActivity::TYPE_CREATED, 'QR code created', [], $actor?->id);

            if ($qrCode->user_id) {
                $qrCode->logActivity(QrCodeActivity::TYPE_ASSIGNED, 'Assigned on creation', [
                    'to_user_id' => $qrCode->user_id,
                ], $actor?->id);
            }

            return $qrCode;
        });
    }

    /**
     * Point codes at a customer. Existing scan history stays with the code, but
     * the denormalised owner on past scans is repointed so the new owner's
     * reporting matches what they actually see in their list.
     *
     * @param  Collection<int, QrCode>|array<int, QrCode>  $qrCodes
     */
    public function assign(iterable $qrCodes, User $user, ?User $actor = null): int
    {
        $count = 0;

        DB::transaction(function () use ($qrCodes, $user, $actor, &$count) {
            foreach ($qrCodes as $qrCode) {
                $previous = $qrCode->user_id;

                if ($previous === $user->id) {
                    continue;
                }

                $qrCode->forceFill([
                    'user_id' => $user->id,
                    'assigned_at' => now(),
                ])->save();

                $qrCode->scans()->update(['user_id' => $user->id]);

                $qrCode->logActivity(
                    QrCodeActivity::TYPE_ASSIGNED,
                    'Assigned to '.$user->name,
                    ['from_user_id' => $previous, 'to_user_id' => $user->id],
                    $actor?->id,
                );

                $count++;
            }
        });

        return $count;
    }

    /** Return codes to the unassigned pool. */
    public function unassign(iterable $qrCodes, ?User $actor = null): int
    {
        $count = 0;

        DB::transaction(function () use ($qrCodes, $actor, &$count) {
            foreach ($qrCodes as $qrCode) {
                if ($qrCode->user_id === null) {
                    continue;
                }

                $previous = $qrCode->user_id;

                $qrCode->forceFill(['user_id' => null, 'assigned_at' => null])->save();
                $qrCode->scans()->update(['user_id' => null]);

                $qrCode->logActivity(
                    QrCodeActivity::TYPE_UNASSIGNED,
                    'Returned to the unassigned pool',
                    ['from_user_id' => $previous],
                    $actor?->id,
                );

                $count++;
            }
        });

        return $count;
    }

    /** The core dynamic-QR operation: repoint a printed code at a new destination. */
    public function updateTargetUrl(QrCode $qrCode, ?string $url, ?User $actor = null): QrCode
    {
        $previous = $qrCode->target_url;
        $url = filled($url) ? trim($url) : null;

        if ($previous === $url) {
            return $qrCode;
        }

        $qrCode->forceFill(['target_url' => $url])->save();

        $qrCode->logActivity(
            QrCodeActivity::TYPE_URL_CHANGED,
            $previous ? 'Destination changed' : 'Destination set',
            ['from' => $previous, 'to' => $url],
            $actor?->id,
        );

        return $qrCode;
    }

    public function setActive(iterable $qrCodes, bool $active, ?User $actor = null): int
    {
        $count = 0;

        foreach ($qrCodes as $qrCode) {
            if ($qrCode->is_active === $active) {
                continue;
            }

            $qrCode->forceFill(['is_active' => $active])->save();

            $qrCode->logActivity(
                $active ? QrCodeActivity::TYPE_ACTIVATED : QrCodeActivity::TYPE_DEACTIVATED,
                $active ? 'QR code resumed' : 'QR code paused',
                [],
                $actor?->id,
            );

            $count++;
        }

        return $count;
    }

    private function buildLabel(?string $prefix, int $index): ?string
    {
        if (blank($prefix)) {
            return null;
        }

        return trim($prefix).' '.$index;
    }

    /** @param  Collection<int, QrCode>  $qrCodes */
    private function logBulk(Collection $qrCodes, string $type, string $description, array $meta, ?User $actor): void
    {
        $now = now();

        $rows = $qrCodes->map(fn (QrCode $qrCode) => [
            'qr_code_id' => $qrCode->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
            'meta' => $meta ? json_encode($meta) : null,
            'ip_address' => request()->ip(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            QrCodeActivity::insert($chunk);
        }
    }
}
