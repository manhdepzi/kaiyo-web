<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Support;

use Illuminate\Validation\Rules\Password;

trait PasswordRules
{
    /** @return array<int, mixed> */
    private function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            'confirmed',
        ];
    }
}
