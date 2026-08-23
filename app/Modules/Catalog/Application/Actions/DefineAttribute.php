<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\AttributeDefinition;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class DefineAttribute
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, string $code, string $name, string $type, bool $filterable): AttributeDefinition
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        $code = mb_strtolower(trim($code), 'UTF-8');
        if (preg_match('/^[a-z][a-z0-9_]{0,99}$/', $code) !== 1 || trim($name) === '' || ! in_array($type, ['text', 'integer', 'decimal', 'boolean'], true)) {
            throw new DomainException('Attribute definition is invalid.');
        }

        return AttributeDefinition::query()->create(['code' => $code, 'name' => trim($name), 'value_type' => $type, 'filterable' => $filterable, 'status' => 'active']);
    }
}
