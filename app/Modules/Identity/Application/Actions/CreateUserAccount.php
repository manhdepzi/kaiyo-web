<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Support\NormalizesEmail;
use App\Modules\Identity\Application\Support\PasswordRules;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateUserAccount implements CreatesNewUsers
{
    use NormalizesEmail;
    use PasswordRules;

    /** @param array<string, string> $input */
    public function create(array $input): UserAccount
    {
        $displayEmail = trim($input['email_normalized'] ?? '');
        $normalizedEmail = $this->normalizeEmail($displayEmail);
        $input['email_normalized'] = $normalizedEmail;

        Validator::make($input, [
            'email_normalized' => [
                'required', 'string', 'email:rfc', 'max:320',
                Rule::unique('user_accounts', 'email_normalized'),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return UserAccount::query()->create([
            'email_display' => $displayEmail,
            'email_normalized' => $normalizedEmail,
            'password_hash' => Hash::make($input['password']),
            'status' => 'pending',
        ]);
    }
}
