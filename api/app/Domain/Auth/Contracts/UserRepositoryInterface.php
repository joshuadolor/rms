<?php

namespace App\Domain\Auth\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;
}
