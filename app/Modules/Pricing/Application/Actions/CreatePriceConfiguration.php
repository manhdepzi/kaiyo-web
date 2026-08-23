<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreatePriceConfiguration
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @param list<array{variant_id: int, layer: string, scope_type?: string, scope_id?: int|null, priority: int, unit_amount: int, minimum_quantity?: string, source_reference: string}> $rules */
    public function execute(UserAccount $actor, array $rules, ?PriceConfiguration $previous = null): PriceConfiguration
    {
        if (! $this->authorizer->allows($actor, 'pricing.rules.manage', AuthorizationScope::module('pricing')) || ! $actor->hasEnabledTwoFactorAuthentication()) {
            throw new AuthorizationException('The actor cannot propose pricing configuration.');
        }
        if ($rules === []) {
            throw new DomainException('Pricing configuration requires rules.');
        }

        return DB::transaction(function () use ($actor, $rules, $previous): PriceConfiguration {
            $configuration = PriceConfiguration::query()->create([
                'lineage_id' => $previous?->lineage_id,
                'revision_no' => $previous === null ? 1 : $previous->revision_no + 1,
                'status' => 'draft',
                'proposed_by_user_account_id' => $actor->getKey(),
            ]);
            foreach ($rules as $rule) {
                if (! in_array($rule['layer'], ['base', 'b2b', 'override', 'quotation'], true) || $rule['unit_amount'] < 0 || trim($rule['source_reference']) === '') {
                    throw new DomainException('Price rule is invalid.');
                }
                PriceRule::query()->create([
                    'price_configuration_id' => $configuration->getKey(),
                    'variant_id' => $rule['variant_id'],
                    'layer' => $rule['layer'],
                    'scope_type' => $rule['scope_type'] ?? 'global',
                    'scope_id' => $rule['scope_id'] ?? null,
                    'priority' => $rule['priority'],
                    'unit_amount' => $rule['unit_amount'],
                    'currency' => 'VND',
                    'minimum_quantity' => $rule['minimum_quantity'] ?? '0.0001',
                    'source_reference' => trim($rule['source_reference']),
                ]);
            }

            return $configuration->load('rules');
        }, 3);
    }
}
