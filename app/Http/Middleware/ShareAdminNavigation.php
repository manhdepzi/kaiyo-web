<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class ShareAdminNavigation
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        View::share('adminNavigation', $account instanceof UserAccount ? [
            'content' => $this->authorizer->allows($account, 'content.manage', AuthorizationScope::module('content')),
            'content_publish' => $this->authorizer->allows($account, 'content.publish', AuthorizationScope::module('content')),
            'audit' => $this->authorizer->allows($account, 'system.audit.read', AuthorizationScope::module('system')),
            'outbox' => $this->authorizer->allows($account, 'system.audit.read', AuthorizationScope::module('system')),
            'merchant' => $this->authorizer->allows($account, 'merchant.manage', AuthorizationScope::module('system')),
            'analytics' => $this->authorizer->allows($account, 'analytics.read', AuthorizationScope::module('analytics')),
            'catalog' => $this->authorizer->allows($account, 'catalog.products.manage', AuthorizationScope::module('catalog')),
        ] : []);

        return $next($request);
    }
}
