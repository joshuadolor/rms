<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboEntry extends Model
{
    protected $fillable = [
        'combo_menu_item_id',
        'referenced_menu_item_id',
        'variant_id',
        'quantity',
        'modifier_label',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function comboMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'combo_menu_item_id');
    }

    public function referencedMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'referenced_menu_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariantSku::class, 'variant_id');
    }
}
