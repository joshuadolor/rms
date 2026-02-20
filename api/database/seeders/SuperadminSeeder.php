<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Create or update the superadmin user from SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD.
     * Sets is_superadmin = true and ensures email is verified so they can log in via POST /api/login.
     */
    public function run(): void
    {
        $email = config('superadmin.email');
        $password = config('superadmin.password');

        if (empty($email) || empty($password)) {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'password' => Hash::make($password),
                'is_superadmin' => true,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            User::query()->create([
                'name' => 'Superadmin',
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_superadmin' => true,
                'is_active' => true,
                'is_paid' => false,
            ]);
        }
    }
}
