<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Services\SlugRedirectService;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategory
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private SlugRedirectService $redirects, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, Category $category, int $expectedVersion, ?string $name = null, ?string $slug = null, ?Category $parent = null, bool $moveToRoot = false, ?string $status = null): Category
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if ($parent?->is($category) || ($parent !== null && $this->isDescendant($parent, $category))) {
            throw new DomainException('Category hierarchy would contain a cycle.');
        }
        if ($status !== null && ! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Category status is invalid.');
        }

        return DB::transaction(function () use ($category, $expectedVersion, $name, $slug, $parent, $moveToRoot, $status): Category {
            $locked = Category::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Category was changed by another request.');
            }
            $oldSlug = $locked->slug;
            $newSlug = $slug === null ? $oldSlug : $this->identity->slug($slug);
            $locked->forceFill([
                'name' => $name === null ? $locked->name : trim($name),
                'slug' => $newSlug,
                'parent_id' => $moveToRoot ? null : ($parent?->getKey() ?? $locked->parent_id),
                'status' => $status ?? $locked->status,
                'lock_version' => $expectedVersion + 1,
            ])->save();
            if ($newSlug !== $oldSlug) {
                $this->redirects->replace('category', (int) $locked->getKey(), '/danh-muc/'.$oldSlug, '/danh-muc/'.$newSlug);
                $this->events->record('category', (int) $locked->getKey(), $locked->lock_version, 'catalog.slug_changed', ['from' => $oldSlug, 'to' => $newSlug]);
            } else {
                $this->events->record('category', (int) $locked->getKey(), $locked->lock_version, 'catalog.updated');
            }

            return $locked->refresh();
        }, 3);
    }

    private function isDescendant(Category $candidate, Category $category): bool
    {
        $visited = [];
        $current = $candidate;
        while ($current->parent_id !== null) {
            if (isset($visited[$current->getKey()])) {
                throw new DomainException('Existing category hierarchy is cyclic.');
            }
            $visited[$current->getKey()] = true;
            if ($current->parent_id === $category->getKey()) {
                return true;
            }
            $current = Category::query()->findOrFail($current->parent_id);
        }

        return false;
    }
}
