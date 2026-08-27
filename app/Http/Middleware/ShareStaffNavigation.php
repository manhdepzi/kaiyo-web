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

final readonly class ShareStaffNavigation
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        $scope = AuthorizationScope::module('crm');
        View::share('staffNavigation', $account instanceof UserAccount ? [
            'customers' => $this->authorizer->allows($account, 'crm.customers.read', $scope),
            'leads' => $this->authorizer->allows($account, 'crm.leads.read', $scope),
            'companies' => $this->authorizer->allows($account, 'crm.companies.read', $scope),
            'can_create_leads' => $this->authorizer->allows($account, 'crm.leads.create', $scope),
            'can_create_companies' => $this->authorizer->allows($account, 'crm.companies.create', $scope),
            'quotes' => $this->authorizer->allows($account, 'quotes.read', AuthorizationScope::module('quotes')),
            'orders' => $this->authorizer->allows($account, 'orders.read', AuthorizationScope::module('orders')),
        ] : []);

        return $next($request);
    }
}
