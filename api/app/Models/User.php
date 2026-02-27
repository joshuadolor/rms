<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_paid', // reserved for future paid features (e.g. multiple restaurants)
        'is_superadmin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization (id is internal-only; API uses uuid).
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_paid' => 'boolean',
            'is_superadmin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = (config('app.frontend_url') ?: config('app.url')) . '/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);
        $this->notify(new \App\Notifications\ResetPasswordNotification($url));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    /**
     * When sending VerifyNewEmailNotification, send to pending_email (the new address).
     */
    public function routeNotificationForMail(object $notification): mixed
    {
        if ($notification instanceof \App\Notifications\VerifyNewEmailNotification && $this->pending_email) {
            return $this->pending_email;
        }

        return $this->email;
    }
}
