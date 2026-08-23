<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePermission
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $permission, ?string $module = null): Response
    {
        $account = $request->user();
        $scope = $module === null ? AuthorizationScope::global() : AuthorizationScope::module($module);

        abort_unless(
            $account instanceof UserAccount && $this->authorizer->allows($account, $permission, $scope),
            403,
        );

        return $next($request);
    }
}
