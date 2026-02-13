<?php

namespace App\Application\Auth;

use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * @param array{name: string, email: string, password: string} $input
     * @return array{user: User, token: string}
     */
    public function handle(array $input): array
    {
        $user = $this->userRepository->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
