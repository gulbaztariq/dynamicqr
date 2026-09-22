<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QrCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'qr_batch_id',
        'user_id',
        'label',
        'target_url',
        'is_active',
        'user_can_edit',
        'design',
        'notes',
        'created_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'user_can_edit' => 'boolean',
            'design' => 'array',
            'scan_count' => 'integer',
            'unique_scan_count' => 'integer',
            'last_scanned_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $qrCode) {
            $qrCode->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QrBatch::class, 'qr_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(QrCodeActivity::class)->latest();
    }

    /** The permanent short link that gets printed onto the product. */
    public function getShortUrlAttribute(): string
    {
        return route('qr.redirect', ['code' => $this->code]);
    }

    public function getIsAssignedAttribute(): bool
    {
        return $this->user_id !== null;
    }

    /** A QR only sends visitors somewhere once it is active AND has a destination. */
    public function getIsLiveAttribute(): bool
    {
        return $this->is_active && filled($this->target_url);
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Paused',
            blank($this->target_url) => 'No destination',
            default => 'Live',
        };
    }

    public function statusClasses(): string
    {
        return match (true) {
            ! $this->is_active => 'bg-amber-100 text-amber-700 ring-amber-600/20',
            blank($this->target_url) => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            default => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20',
        };
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** Free text search across code, label and destination. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code', 'like', $like)
                ->orWhere('label', 'like', $like)
                ->orWhere('target_url', 'like', $like);
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'live' => $query->where('is_active', true)->whereNotNull('target_url'),
            'paused' => $query->where('is_active', false),
            'unconfigured' => $query->whereNull('target_url'),
            'assigned' => $query->whereNotNull('user_id'),
            'unassigned' => $query->whereNull('user_id'),
            default => $query,
        };
    }

    public function logActivity(string $type, ?string $description = null, array $meta = [], ?int $actorId = null): QrCodeActivity
    {
        return $this->activities()->create([
            'actor_id' => $actorId ?? auth()->id(),
            'type' => $type,
            'description' => $description,
            'meta' => $meta ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
