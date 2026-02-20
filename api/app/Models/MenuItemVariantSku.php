<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuItemVariantSku extends Model
{
    protected $fillable = ['menu_item_id', 'option_values', 'price', 'image_url'];

    protected $casts = [
        'option_values' => 'array',
        'price' => 'decimal:2',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (MenuItemVariantSku $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /** Combo entries that reference this variant. */
    public function comboEntries(): HasMany
    {
        return $this->hasMany(ComboEntry::class, 'variant_id');
    }
}
