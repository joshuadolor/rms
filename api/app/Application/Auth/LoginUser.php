<?php

namespace App\Application\Auth;

use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class LoginUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * @param array{email: string, password: string} $input
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function handle(array $input): array
    {
        $user = $this->userRepository->findByEmail($input['email']);

        if (! $user || ! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
