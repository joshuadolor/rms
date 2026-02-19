<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    protected $fillable = [
        'user_id',
        'restaurant_id',
        'category_id',
        'sort_order',
        'price',
        'source_menu_item_uuid',
        'price_override',
        'translation_overrides',
    ];

    protected $casts = [
        'translation_overrides' => 'array',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (MenuItem $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isStandalone(): bool
    {
        return $this->restaurant_id === null;
    }

    /** When this item is a restaurant usage of a catalog item. */
    public function sourceMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'source_menu_item_uuid', 'uuid');
    }

    /** Effective price: override if set, else base from source or this item. */
    public function getEffectivePrice(): ?float
    {
        if ($this->source_menu_item_uuid !== null) {
            $override = $this->price_override;
            if ($override !== null) {
                return (float) $override;
            }
            $source = $this->sourceMenuItem;
            return $source ? (float) $source->price : null;
        }
        return $this->price !== null ? (float) $this->price : null;
    }

    /** Effective translations: merge base (from source or this) with translation_overrides. */
    public function getEffectiveTranslations(): array
    {
        $base = [];
        if ($this->source_menu_item_uuid !== null && $this->relationLoaded('sourceMenuItem') && $this->sourceMenuItem) {
            foreach ($this->sourceMenuItem->translations as $t) {
                $base[$t->locale] = ['name' => $t->name ?? '', 'description' => $t->description];
            }
        } else {
            foreach ($this->translations as $t) {
                $base[$t->locale] = ['name' => $t->name ?? '', 'description' => $t->description];
            }
        }
        $overrides = $this->translation_overrides ?? [];
        foreach ($overrides as $locale => $data) {
            $base[$locale] = [
                'name' => $data['name'] ?? $base[$locale]['name'] ?? '',
                'description' => array_key_exists('description', $data) ? $data['description'] : ($base[$locale]['description'] ?? null),
            ];
        }
        return $base;
    }

    public function hasOverrides(): bool
    {
        if ($this->source_menu_item_uuid === null) {
            return false;
        }
        return $this->price_override !== null
            || ! empty($this->translation_overrides);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<MenuItemTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class, 'menu_item_id');
    }
}
