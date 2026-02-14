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
     * Create user, send verification email. No token is issued until email is verified.
     *
     * @param array{name: string, email: string, password: string} $input
     * @return array{user: User}
     */
    public function handle(array $input): array
    {
        $user = $this->userRepository->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
        ];
    }
}
