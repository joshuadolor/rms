<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Restaurant extends Model
{
    /**
     * user_id, logo_path, and banner_path are set only in use cases / repository (forceFill), not from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tagline',
        'primary_color',
        'slug',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'social_links',
        'default_locale',
        'currency',
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
        'social_links' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::creating(function (Restaurant $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RestaurantLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(RestaurantLanguage::class);
    }

    /**
     * @return HasMany<RestaurantTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(RestaurantTranslation::class);
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Whether this restaurant is owned by the given user (by id or User model).
     */
    public function isOwnedBy(int|User $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (int) $this->user_id === (int) $userId;
    }
}
