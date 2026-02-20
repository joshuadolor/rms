<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'sort_order',
        'is_active',
        'availability',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'availability' => 'array',
    ];

    /**
     * Internal id never exposed in API; use uuid.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return HasMany<CategoryTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id')->orderBy('sort_order')->orderBy('id');
    }
}
