<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class RestaurantRepository implements RestaurantRepositoryInterface
{
    public function create(array $attributes): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->forceFill($attributes)->save();

        return $restaurant;
    }

    public function update(Restaurant $restaurant, array $attributes): Restaurant
    {
        $restaurant->forceFill($attributes)->save();

        return $restaurant->fresh();
    }

    public function countForUser(User|int $userOrId): int
    {
        $userId = $userOrId instanceof User ? $userOrId->id : (int) $userOrId;

        return Restaurant::query()->where('user_id', $userId)->count();
    }

    public function findById(int $id): ?Restaurant
    {
        return Restaurant::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Restaurant
    {
        return Restaurant::query()->where('uuid', $uuid)->first();
    }

    public function findBySlug(string $slug): ?Restaurant
    {
        return Restaurant::query()->where('slug', $slug)->first();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Restaurant::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function delete(Restaurant $restaurant): bool
    {
        return $restaurant->delete();
    }
}
