<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class MenuItemTag extends Model
{
    protected $fillable = [
        'uuid',
        'color',
        'icon',
        'text',
        'user_id',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (MenuItemTag $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<MenuItem, $this> */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_menu_item_tag')
            ->withTimestamps();
    }

    public function isDefault(): bool
    {
        return $this->user_id === null;
    }

    public function scopeDefaultTags($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeCustomForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * UUIDs of tags the user can assign to menu items (default tags only; custom tags no longer supported).
     *
     * @return array<string>
     */
    public static function usableUuidsForUser(User $user): array
    {
        return self::query()
            ->whereNull('user_id')
            ->pluck('uuid')
            ->all();
    }

    /**
     * Validate tag_uuids and return tag IDs for sync. Throws ValidationException if any UUID is invalid.
     *
     * @param  array<string>  $tagUuids
     * @return array<int>
     */
    public static function validateAndResolveIdsForUser(User $user, array $tagUuids): array
    {
        if ($tagUuids === []) {
            return [];
        }
        $usableUuids = self::usableUuidsForUser($user);
        $invalid = array_diff($tagUuids, $usableUuids);
        if ($invalid !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tag_uuids' => [__('One or more tag UUIDs are invalid or not available to you.')],
            ]);
        }

        return self::query()
            ->whereIn('uuid', $tagUuids)
            ->pluck('id')
            ->all();
    }

    /**
     * API payload for a single tag (uuid, color, icon, text, is_default). No internal id.
     * is_default: true for system tags (read-only in UI); false for user's custom tags.
     *
     * @return array{uuid: string, color: string, icon: string, text: string, is_default: bool}
     */
    public function toTagPayload(): array
    {
        return [
            'uuid' => $this->uuid,
            'color' => $this->color ?? '#6b7280',
            'icon' => $this->icon ?? '',
            'text' => $this->text ?? '',
            'is_default' => $this->isDefault(),
        ];
    }
}
