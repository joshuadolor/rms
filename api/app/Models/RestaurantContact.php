<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RestaurantContact extends Model
{
    protected $table = 'restaurant_contacts';

    /** Phone-like contact types: value holds phone number. */
    public const TYPES_PHONE = ['whatsapp', 'mobile', 'phone', 'fax', 'other'];

    /** Link/social types: value holds URL. */
    public const TYPES_LINK = ['facebook', 'instagram', 'twitter', 'website'];

    /** All allowed types (Contact & links module). */
    public const TYPES = [...self::TYPES_PHONE, ...self::TYPES_LINK];

    protected $fillable = [
        'type',
        'value',
        'number',
        'label',
        'is_active',
    ];

    /**
     * Internal id never exposed in API; use uuid.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (RestaurantContact $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Scope to only active contacts (for public display).
     *
     * @param  Builder<RestaurantContact>  $query
     * @return Builder<RestaurantContact>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether this type stores a URL (link type). */
    public static function isLinkType(string $type): bool
    {
        return in_array($type, self::TYPES_LINK, true);
    }

    /** Effective value for API: value if set, else legacy number (phone types). */
    public function getEffectiveValue(): ?string
    {
        return $this->value ?? $this->number;
    }
}
