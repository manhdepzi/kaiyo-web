<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<UserAccount> */
final class UserAccountFactory extends Factory
{
    protected $model = UserAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'email_display' => $email,
            'email_normalized' => mb_strtolower($email, 'UTF-8'),
            'password_hash' => Hash::make('ValidPassword!123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'status' => 'pending',
            'email_verified_at' => null,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => [
            'status' => 'disabled',
            'disabled_at' => now(),
        ]);
    }
}
