<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Application\Actions\CreateCategory;
use App\Modules\Catalog\Application\Actions\CreateProduct;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Pricing\Application\Actions\ActivatePriceConfiguration;
use App\Modules\Pricing\Application\Actions\CreatePriceConfiguration;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use App\Modules\Pricing\Application\Services\PricingSnapshotStore;
use App\Modules\Pricing\Domain\PriceCandidate;
use App\Modules\Pricing\Domain\PricingEngine;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PricingGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_requires_distinct_authorized_two_factor_approver(): void
    {
        [$variant, $proposer] = $this->variantAndProposer();
        $configuration = app(CreatePriceConfiguration::class)->execute($proposer, [$this->baseRule((int) $variant->getKey(), 100_000)]);

        try {
            app(ActivatePriceConfiguration::class)->execute($proposer, $configuration, 0);
            self::fail('Self approval must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $approver = $this->actorWith('pricing.rules.manage');
        $active = app(ActivatePriceConfiguration::class)->execute($approver, $configuration, 0);
        self::assertSame('active', $active->status);
        self::assertSame($approver->getKey(), $active->approved_by_user_account_id);
        self::assertSame(1, $active->lock_version);
    }

    public function test_ambiguous_same_layer_priority_fails_activation(): void
    {
        [$variant, $proposer] = $this->variantAndProposer();
        $rule = $this->baseRule((int) $variant->getKey(), 100_000);
        $other = [...$rule, 'unit_amount' => 99_000, 'source_reference' => 'base-duplicate'];
        $configuration = app(CreatePriceConfiguration::class)->execute($proposer, [$rule, $other]);

        $this->expectException(DomainException::class);
        app(ActivatePriceConfiguration::class)->execute($this->actorWith('pricing.rules.manage'), $configuration, 0);
    }

    public function test_snapshot_remains_unchanged_after_later_configuration_activation(): void
    {
        [$variant, $proposer] = $this->variantAndProposer();
        $approver = $this->actorWith('pricing.rules.manage');
        $create = app(CreatePriceConfiguration::class);
        $activate = app(ActivatePriceConfiguration::class);
        $first = $activate->execute($approver, $create->execute($proposer, [$this->baseRule((int) $variant->getKey(), 100_000)]), 0);
        $result = (new PricingEngine)->calculate([new PriceCandidate('base', 1, 100_000, 'base-r1')], '2');
        $snapshot = app(PricingSnapshotStore::class)->persist($first, $variant, $result);

        $secondDraft = $create->execute($proposer, [$this->baseRule((int) $variant->getKey(), 120_000)], $first);
        $activate->execute($approver, $secondDraft, 0);
        $stored = \DB::table('pricing_calculation_snapshots')->where('id', $snapshot->id)->firstOrFail();

        self::assertSame(100_000, (int) $stored->unit_amount);
        self::assertSame(200_000, (int) $stored->line_amount);
        self::assertSame('superseded', $first->refresh()->status);
        self::assertSame(1, \DB::table('pricing_calculation_snapshots')->count());
    }

    public function test_database_resolver_applies_active_revision_promotion_then_later_b2b_layer(): void
    {
        [$variant, $proposer] = $this->variantAndProposer();
        $rules = [
            $this->baseRule((int) $variant->getKey(), 100_000),
            ['variant_id' => (int) $variant->getKey(), 'layer' => 'b2b', 'priority' => 1, 'unit_amount' => 80_000, 'source_reference' => 'tier-r1'],
        ];
        $active = app(ActivatePriceConfiguration::class)->execute(
            $this->actorWith('pricing.rules.manage'),
            app(CreatePriceConfiguration::class)->execute($proposer, $rules),
            0,
        );
        \DB::table('promotions')->insert([
            'public_id' => (string) \Str::ulid(),
            'price_configuration_id' => $active->getKey(),
            'type' => 'percentage',
            'fixed_amount' => null,
            'percentage_micros' => 100_000,
            'priority' => 1,
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(DatabasePricingResolver::class)->resolve($variant, '2.5000');
        self::assertSame(80_000, $result->unitAmount);
        self::assertSame(200_000, $result->lineAmount);
        self::assertSame(['base', 'promotion', 'b2b'], array_column($result->resolution, 'layer'));
    }

    /** @return array{0: Variant, 1: UserAccount} */
    private function variantAndProposer(): array
    {
        $catalogActor = $this->actorWith('catalog.products.manage');
        $category = app(CreateCategory::class)->execute($catalogActor, 'Pricing Products');
        $product = app(CreateProduct::class)->execute($catalogActor, $category, 'Priced Product', [['sku' => 'PRICE-'.random_int(1000, 9999), 'name' => 'Default']]);

        return [$product->variants->firstOrFail(), $this->actorWith('pricing.rules.manage')];
    }

    /** @return array{variant_id: int, layer: string, priority: int, unit_amount: int, source_reference: string} */
    private function baseRule(int $variantId, int $amount): array
    {
        return ['variant_id' => $variantId, 'layer' => 'base', 'priority' => 1, 'unit_amount' => $amount, 'source_reference' => 'base-revision'];
    }

    private function actorWith(string $permissionCode): UserAccount
    {
        $actor = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('pricing-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['pricing-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ]);
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::global()->persistenceValues(),
            'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Pricing test authority.',
            'identity_hash' => hash('sha256', $actor->getKey().'|'.$permission->getKey().'|'.random_bytes(8), true),
        ]);

        return $actor;
    }
}
