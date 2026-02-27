<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_active',
        'is_available',
        'availability',
        'price',
        'type',
        'combo_price',
        'source_menu_item_uuid',
        'source_variant_uuid',
        'image_path',
        'price_override',
        'translation_overrides',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'availability' => 'array',
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

    /** When this item is tied to a specific variant of the source catalog item (type with_variants). */
    public function sourceVariantSku(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariantSku::class, 'source_variant_uuid', 'uuid');
    }

    /** Effective price: override if set, else base from source or this item. When source_variant_uuid is set, base is variant price. Combo: combo_price if set. With_variants: no single price. */
    public function getEffectivePrice(): ?float
    {
        if ($this->source_menu_item_uuid !== null) {
            $override = $this->price_override;
            if ($override !== null) {
                return (float) $override;
            }
            if ($this->source_variant_uuid !== null && $this->relationLoaded('sourceVariantSku') && $this->sourceVariantSku) {
                return (float) $this->sourceVariantSku->price;
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

    /** Human-readable label from variant option_values (e.g. "Hawaiian, Small"). Returns null if no variant. */
    public function getVariantLabel(): ?string
    {
        if ($this->source_variant_uuid === null || ! $this->relationLoaded('sourceVariantSku') || ! $this->sourceVariantSku) {
            return null;
        }
        $values = $this->sourceVariantSku->option_values;
        if (! is_array($values)) {
            return null;
        }
        return implode(', ', array_values($values));
    }

    /** Effective translations: merge base (from source or this) with translation_overrides. When source_variant_uuid is set, name is base name + variant label. */
    public function getEffectiveTranslations(): array
    {
        $base = [];
        if ($this->source_menu_item_uuid !== null && $this->relationLoaded('sourceMenuItem') && $this->sourceMenuItem) {
            $variantLabel = $this->getVariantLabel();
            foreach ($this->sourceMenuItem->translations as $t) {
                $name = $t->name ?? '';
                if ($variantLabel !== null && $variantLabel !== '') {
                    $name = $name . ' - ' . $variantLabel;
                }
                $base[$t->locale] = ['name' => $name, 'description' => $t->description];
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

    /** @return BelongsToMany<MenuItemTag, $this> */
    public function menuItemTags(): BelongsToMany
    {
        return $this->belongsToMany(MenuItemTag::class, 'menu_item_menu_item_tag')
            ->withTimestamps();
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
