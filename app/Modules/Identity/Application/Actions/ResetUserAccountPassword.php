<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Support\PasswordRules;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserAccountPassword implements ResetsUserPasswords
{
    use PasswordRules;

    /** @param array<string, string> $input */
    public function reset(UserAccount $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();

        $user->forceFill(['password_hash' => Hash::make($input['password'])])->save();
    }
}
