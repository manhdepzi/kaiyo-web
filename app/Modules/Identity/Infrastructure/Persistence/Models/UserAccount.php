<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserAccountFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $public_id
 * @property string $email_display
 * @property string $email_normalized
 * @property string $password_hash
 * @property string $status
 * @property int $lock_version
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $two_factor_enabled_at
 * @property CarbonImmutable|null $disabled_at
 */
final class UserAccount extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserAccountFactory> */
    use HasFactory;

    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $table = 'user_accounts';

    protected $fillable = [
        'email_display',
        'email_normalized',
        'password_hash',
        'status',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $account): void {
            $account->public_id = $account->public_id ?: (string) Str::ulid();
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getEmailForVerification(): string
    {
        return $this->email_normalized;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email_normalized;
    }

    public function routeNotificationForMail(mixed $notification = null): string
    {
        return $this->email_display;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->disabled_at === null;
    }

    protected static function newFactory(): UserAccountFactory
    {
        return UserAccountFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_enabled_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'password_hash' => 'hashed',
            'lock_version' => 'integer',
        ];
    }
}
