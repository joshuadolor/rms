<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariantOptionGroup extends Model
{
    protected $fillable = ['menu_item_id', 'name', 'values', 'sort_order'];

    protected $casts = [
        'values' => 'array',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
