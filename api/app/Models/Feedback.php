<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'rating',
        'text',
        'name',
        'is_approved',
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
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Feedback $model): void {
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
     * Scope to only approved feedbacks (for public display).
     *
     * @param  Builder<Feedback>  $query
     * @return Builder<Feedback>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
