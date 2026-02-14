<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;

final class UserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }
}
