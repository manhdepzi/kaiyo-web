<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $user_account_id
 * @property string $token_hash
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $user_agent_redacted
 */
final class AuthSession extends Model
{
    protected $fillable = [
        'user_account_id',
        'token_hash',
        'last_seen_at',
        'expires_at',
        'revoked_at',
        'ip_hash',
        'user_agent_redacted',
    ];

    protected $hidden = ['token_hash', 'ip_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $session): void {
            $session->public_id = $session->public_id ?: (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
