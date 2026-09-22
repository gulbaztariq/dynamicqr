<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeActivity extends Model
{
    use HasFactory;

    public const TYPE_CREATED = 'created';

    public const TYPE_ASSIGNED = 'assigned';

    public const TYPE_UNASSIGNED = 'unassigned';

    public const TYPE_URL_CHANGED = 'url_changed';

    public const TYPE_ACTIVATED = 'activated';

    public const TYPE_DEACTIVATED = 'deactivated';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_DELETED = 'deleted';

    protected $fillable = [
        'qr_code_id',
        'actor_id',
        'type',
        'description',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function icon(): string
    {
        return match ($this->type) {
            self::TYPE_URL_CHANGED => 'link',
            self::TYPE_ASSIGNED, self::TYPE_UNASSIGNED => 'user',
            self::TYPE_ACTIVATED, self::TYPE_DEACTIVATED => 'power',
            self::TYPE_DELETED => 'trash',
            default => 'dot',
        };
    }
}
