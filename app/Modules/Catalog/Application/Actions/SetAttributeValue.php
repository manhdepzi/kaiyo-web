<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\AttributeDefinition;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class SetAttributeValue
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, AttributeDefinition $definition, string|int|bool $value, ?Product $product = null, ?Variant $variant = null): void
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if (($product === null) === ($variant === null) || $definition->status !== 'active') {
            throw new DomainException('Attribute owner or definition is invalid.');
        }
        $typed = $this->typedValue($definition->value_type, $value);
        $ownerType = $product === null ? 'variant' : 'product';
        $ownerId = (int) ($product?->getKey() ?? $variant?->getKey());
        $identity = hash('sha256', $definition->getKey().'|'.$ownerType.'|'.$ownerId, true);
        DB::table('product_attribute_values')->updateOrInsert(
            ['identity_hash' => $identity],
            [
                'attribute_definition_id' => $definition->getKey(),
                'product_id' => $product?->getKey(),
                'variant_id' => $variant?->getKey(),
                'text_value' => null,
                'integer_value' => null,
                'decimal_value' => null,
                'boolean_value' => null,
                ...$typed,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $this->events->record($ownerType, $ownerId, 0, 'attribute.changed', ['attribute' => $definition->code]);
    }

    /** @return array<string, string|int|bool> */
    private function typedValue(string $type, string|int|bool $value): array
    {
        return match ($type) {
            'text' => is_string($value) && mb_strlen($value) <= 191 ? ['text_value' => $value] : throw new DomainException('Text attribute value is invalid.'),
            'integer' => is_int($value) ? ['integer_value' => $value] : throw new DomainException('Integer attribute value is invalid.'),
            'decimal' => is_string($value) && preg_match('/^-?[0-9]{1,16}(\.[0-9]{1,4})?$/', $value) === 1 ? ['decimal_value' => $value] : throw new DomainException('Decimal attribute value is invalid.'),
            'boolean' => is_bool($value) ? ['boolean_value' => $value] : throw new DomainException('Boolean attribute value is invalid.'),
            default => throw new DomainException('Attribute type is invalid.'),
        };
    }
}
