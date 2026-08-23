<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Support\PasswordRules;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

final class UpdateUserAccountPassword implements UpdatesUserPasswords
{
    use PasswordRules;

    /** @param array<string, string> $input */
    public function update(UserAccount $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password_hash' => Hash::make($input['password'])])->save();
    }
}
