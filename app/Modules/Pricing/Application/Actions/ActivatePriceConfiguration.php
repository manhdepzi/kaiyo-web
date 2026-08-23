<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ActivatePriceConfiguration
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $approver, PriceConfiguration $configuration, int $expectedVersion): PriceConfiguration
    {
        if (! $this->authorizer->allows($approver, 'pricing.rules.manage', AuthorizationScope::module('pricing')) || ! $approver->hasEnabledTwoFactorAuthentication()) {
            throw new AuthorizationException('The approver cannot activate pricing configuration.');
        }
        if ($configuration->proposed_by_user_account_id === $approver->getKey()) {
            throw new AuthorizationException('Pricing proposer cannot self-approve.');
        }

        return DB::transaction(function () use ($approver, $configuration, $expectedVersion): PriceConfiguration {
            $locked = PriceConfiguration::query()->whereKey($configuration->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Pricing revision is stale or no longer draft.');
            }
            $ambiguous = $locked->rules()->select(['variant_id', 'layer', 'scope_type', 'scope_id', 'priority'])
                ->groupBy(['variant_id', 'layer', 'scope_type', 'scope_id', 'priority'])->havingRaw('COUNT(*) > 1')->exists();
            if ($ambiguous || ! $locked->rules()->where('layer', 'base')->exists()) {
                throw new DomainException('Pricing revision is ambiguous or lacks a base price.');
            }
            $transitionAt = now()->toImmutable();
            $activeConfigurations = PriceConfiguration::query()->where('status', 'active')->whereKeyNot($locked->getKey())->lockForUpdate()->get();
            foreach ($activeConfigurations as $active) {
                $startsAt = CarbonImmutable::parse((string) $active->starts_at);
                $minimumRepresentableEnd = $startsAt->startOfSecond()->addSecond();
                if ($transitionAt->lt($minimumRepresentableEnd)) {
                    $transitionAt = $minimumRepresentableEnd;
                }
                $active->forceFill(['status' => 'superseded', 'ends_at' => $transitionAt])->save();
            }
            $locked->forceFill([
                'status' => 'active',
                'starts_at' => $transitionAt,
                'approved_by_user_account_id' => $approver->getKey(),
                'activated_at' => $transitionAt,
                'lock_version' => $expectedVersion + 1,
            ])->save();

            return $locked->refresh();
        }, 3);
    }
}
