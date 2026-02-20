<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OwnerFeedback extends Model
{
    protected $table = 'owner_feedbacks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'restaurant_id',
        'title',
        'message',
        'status',
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
    protected $casts = [];

    protected static function booted(): void
    {
        static::creating(function (OwnerFeedback $model): void {
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
}
