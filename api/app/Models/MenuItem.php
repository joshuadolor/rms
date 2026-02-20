<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_COMBO = 'combo';
    public const TYPE_WITH_VARIANTS = 'with_variants';

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'category_id',
        'sort_order',
        'price',
        'type',
        'combo_price',
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

    /** Effective price: override if set, else base from source or this item. Combo: combo_price if set. With_variants: no single price. */
    public function getEffectivePrice(): ?float
    {
        if ($this->source_menu_item_uuid !== null) {
            $override = $this->price_override;
            if ($override !== null) {
                return (float) $override;
            }
            $source = $this->sourceMenuItem;
            return $source ? $source->getEffectivePrice() : null;
        }
        if ($this->isCombo() && $this->combo_price !== null) {
            return (float) $this->combo_price;
        }
        if ($this->isWithVariants()) {
            return null;
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

    public function variantOptionGroups(): HasMany
    {
        return $this->hasMany(MenuItemVariantOptionGroup::class, 'menu_item_id')->orderBy('sort_order');
    }

    public function variantSkus(): HasMany
    {
        return $this->hasMany(MenuItemVariantSku::class, 'menu_item_id');
    }

    /** When this item is a combo, entries referencing other menu items. */
    public function comboEntries(): HasMany
    {
        return $this->hasMany(ComboEntry::class, 'combo_menu_item_id')->orderBy('sort_order');
    }

    public function isSimple(): bool
    {
        return ($this->type ?? self::TYPE_SIMPLE) === self::TYPE_SIMPLE;
    }

    public function isCombo(): bool
    {
        return $this->type === self::TYPE_COMBO;
    }

    public function isWithVariants(): bool
    {
        return $this->type === self::TYPE_WITH_VARIANTS;
    }
}
