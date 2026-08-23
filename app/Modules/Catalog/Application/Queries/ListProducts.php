<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Queries;

use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListProducts
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @return Collection<int, Product> */
    public function execute(UserAccount $actor, int $limit = 50): Collection
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.read');
        $limit = max(1, min(100, $limit));

        return Product::query()->with(['category', 'brand', 'variants'])->orderBy('id')->limit($limit)->get();
    }
}
