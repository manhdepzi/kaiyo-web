<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateCategory
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, string $name, ?string $slug = null, ?Category $parent = null, int $sortOrder = 0): Category
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if ($sortOrder < 0 || trim($name) === '') {
            throw new DomainException('Category input is invalid.');
        }

        return DB::transaction(function () use ($name, $slug, $parent, $sortOrder): Category {
            $category = Category::query()->create([
                'parent_id' => $parent?->getKey(),
                'name' => trim($name),
                'slug' => $this->identity->slug($slug ?? $name),
                'status' => 'active',
                'sort_order' => $sortOrder,
            ]);
            $this->events->record('category', (int) $category->getKey(), 0, 'catalog.created', ['slug' => $category->slug]);

            return $category;
        });
    }
}
