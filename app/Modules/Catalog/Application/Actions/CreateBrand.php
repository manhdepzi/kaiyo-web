<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateBrand
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, string $name, ?string $slug = null): Brand
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if (trim($name) === '') {
            throw new DomainException('Brand name is required.');
        }

        return DB::transaction(function () use ($name, $slug): Brand {
            $brand = Brand::query()->create(['name' => trim($name), 'slug' => $this->identity->slug($slug ?? $name), 'status' => 'active']);
            $this->events->record('brand', (int) $brand->getKey(), 0, 'catalog.created', ['slug' => $brand->slug]);

            return $brand;
        });
    }
}
