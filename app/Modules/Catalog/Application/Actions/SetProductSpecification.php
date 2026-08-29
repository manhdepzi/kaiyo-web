<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\AttributeDefinition;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Str;

final readonly class SetProductSpecification
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private SetAttributeValue $values) {}

    public function execute(UserAccount $actor, Product $product, string $label, string $value): void
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        $label = trim($label);
        $value = trim($value);
        if ($label === '' || mb_strlen($label) > 160 || $value === '' || mb_strlen($value) > 191) {
            throw new DomainException('Product specification is invalid.');
        }
        $code = 'spec.'.Str::limit(Str::slug($label), 80, '');
        if ($code === 'spec.') {
            $code = 'spec.'.substr(hash('sha256', $label), 0, 16);
        }
        $definition = AttributeDefinition::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $label, 'value_type' => 'text', 'filterable' => false, 'status' => 'active'],
        );
        if ($definition->value_type !== 'text' || $definition->status !== 'active') {
            throw new DomainException('Product specification definition is unavailable.');
        }
        $this->values->execute($actor, $definition, $value, product: $product);
    }
}
