<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Contracts\StaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureStaffTwoFactorEnabled
{
    public function __construct(private StaffAccountClassifier $classifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof UserAccount
            && $this->classifier->isStaff($user)
            && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('account.security')->withErrors([
                'two_factor' => 'Tài khoản nhân viên phải bật xác thực hai lớp.',
            ]);
        }

        return $next($request);
    }
}
