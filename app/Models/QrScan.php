<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_code_id',
        'user_id',
        'ip_hash',
        'country_code',
        'country_name',
        'city',
        'device_type',
        'os',
        'browser',
        'is_bot',
        'is_unique',
        'referrer_host',
        'referrer_url',
        'target_url',
        'user_agent',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'is_unique' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Link previews and crawlers are excluded from reporting by default. */
    public function scopeHumans(Builder $query): Builder
    {
        return $query->where('is_bot', false);
    }
}
