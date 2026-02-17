<?php

namespace App\Domain\Restaurant\Contracts;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RestaurantRepositoryInterface
{
    public function create(array $attributes): Restaurant;

    public function update(Restaurant $restaurant, array $attributes): Restaurant;

    public function findById(int $id): ?Restaurant;

    public function findByUuid(string $uuid): ?Restaurant;

    public function findBySlug(string $slug): ?Restaurant;

    /**
     * Number of restaurants owned by the given user (by id or User model).
     */
    public function countForUser(User|int $userOrId): int;

    /**
     * @return LengthAwarePaginator<Restaurant>
     */
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function delete(Restaurant $restaurant): bool;
}
