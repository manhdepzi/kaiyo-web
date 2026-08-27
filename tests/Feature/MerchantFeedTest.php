<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Growth\Application\Jobs\ProcessMerchantFeedBatchJob;
use App\Modules\Growth\Application\ProcessMerchantFeedBatch;
use App\Modules\Growth\Application\StartMerchantFeedBatch;
use App\Modules\Growth\Contracts\MerchantDestination;
use App\Modules\Growth\Data\MerchantFeedItem;
use App\Modules\Growth\Data\MerchantPublishResult;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MerchantFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_start_requires_authority_and_operation_identity_is_stable(): void
    {
        $outsider = UserAccount::factory()->create();
        try {
            app(StartMerchantFeedBatch::class)->execute($outsider, 'merchant-v1', 'operation-merchant-001');
            self::fail('Merchant batch creation must require server authority.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $actor = $this->merchantActor();
        $first = app(StartMerchantFeedBatch::class)->execute($actor, 'merchant-v1', 'operation-merchant-001');
        $retry = app(StartMerchantFeedBatch::class)->execute($actor, 'merchant-v1', 'operation-merchant-001');
        self::assertSame($first->getKey(), $retry->getKey());

        $this->expectException(DomainException::class);
        app(StartMerchantFeedBatch::class)->execute($actor, 'merchant-v2', 'operation-merchant-001');
    }

    public function test_partial_failure_retries_only_failed_or_changed_authoritative_items(): void
    {
        [$firstVariant, $secondVariant] = $this->sellableVariants();
        $destination = new class implements MerchantDestination
        {
            /** @var list<string> */
            public array $calls = [];

            public function publish(MerchantFeedItem $item): MerchantPublishResult
            {
                $this->calls[] = $item->sku;
                if ($item->sku === 'MERCHANT-B' && count(array_filter($this->calls, fn (string $sku): bool => $sku === 'MERCHANT-B')) === 1) {
                    return MerchantPublishResult::failure('temporary_outage');
                }

                return MerchantPublishResult::success('destination-'.$item->sku);
            }
        };
        $this->app->instance(MerchantDestination::class, $destination);
        $batch = app(StartMerchantFeedBatch::class)->execute($this->merchantActor(), 'merchant-v1', 'operation-merchant-002');

        $partial = app(ProcessMerchantFeedBatch::class)->execute($batch);
        self::assertSame('partial', $partial->state);
        self::assertSame([2, 1, 1], [$partial->total_count, $partial->succeeded_count, $partial->failed_count]);

        $completed = app(ProcessMerchantFeedBatch::class)->execute($partial);
        self::assertSame('completed', $completed->state);
        self::assertSame(['MERCHANT-A', 'MERCHANT-B', 'MERCHANT-B'], $destination->calls);
        self::assertSame(1, DB::table('merchant_feed_item_results')->where('variant_id', $firstVariant->getKey())->value('attempt_count'));
        self::assertSame(2, DB::table('merchant_feed_item_results')->where('variant_id', $secondVariant->getKey())->value('attempt_count'));
        self::assertSame(2, DB::table('merchant_feed_item_results')->where('outcome', 'succeeded')->count());
    }

    public function test_unconfigured_destination_fails_visibly_without_mutating_commerce_truth(): void
    {
        [$variant] = $this->sellableVariants();
        $batch = app(StartMerchantFeedBatch::class)->execute($this->merchantActor(), 'merchant-v1', 'operation-merchant-003');
        $result = app(ProcessMerchantFeedBatch::class)->execute($batch);

        self::assertSame('failed', $result->state);
        self::assertSame('provider_unconfigured', DB::table('merchant_feed_item_results')->where('variant_id', $variant->getKey())->value('error_code'));
        self::assertSame('active', $variant->refresh()->status);
    }

    public function test_admin_delivery_is_private_two_factor_gated_and_queues_batch_work(): void
    {
        Queue::fake();
        $this->actingAs(UserAccount::factory()->create())->get(route('admin.merchant'))->assertForbidden();

        $actor = $this->merchantActor();
        $this->actingAs($actor)->get(route('admin.merchant'))->assertRedirect(route('account.security'));
        $actor->forceFill([
            'two_factor_secret' => encrypt('merchant-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['merchant-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ])->save();

        $this->actingAs($actor)->post(route('admin.merchant.store'), [
            'configuration_revision' => 'merchant-v1',
            'operation_key' => 'operation-merchant-admin-001',
        ])->assertRedirect(route('admin.merchant'));
        $batch = DB::table('merchant_feed_batches')->firstOrFail();
        Queue::assertPushed(ProcessMerchantFeedBatchJob::class, fn (ProcessMerchantFeedBatchJob $job): bool => $job->batchPublicId === $batch->public_id);
        $this->actingAs($actor)->get(route('admin.merchant'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('merchant-v1')->assertSee('pending');
    }

    /** @return array{Variant, Variant} */
    private function sellableVariants(): array
    {
        $category = Category::query()->create(['name' => 'Merchant', 'slug' => 'merchant', 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Merchant Product', 'slug' => 'merchant-product', 'status' => 'active']);
        $first = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'MERCHANT-A', 'name' => 'A', 'status' => 'active']);
        $second = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'MERCHANT-B', 'name' => 'B', 'status' => 'active']);
        $configurationId = DB::table('price_configurations')->insertGetId([
            'public_id' => (string) Str::ulid(), 'lineage_id' => (string) Str::ulid(), 'revision_no' => 1,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'proposed_by_user_account_id' => $this->merchantActor()->getKey(),
            'approved_by_user_account_id' => $this->merchantActor()->getKey(), 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$first, $second] as $index => $variant) {
            DB::table('price_rules')->insert([
                'price_configuration_id' => $configurationId, 'variant_id' => $variant->getKey(), 'layer' => 'base', 'scope_type' => 'global',
                'priority' => 1, 'unit_amount' => 100_000 + ($index * 10_000), 'currency' => 'VND', 'minimum_quantity' => '0.0001',
                'source_reference' => 'merchant-price-v1', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $warehouseId = DB::table('warehouses')->insertGetId([
            'public_id' => (string) Str::ulid(), 'code' => 'MERCHANT-WH', 'name' => 'Merchant Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$first, $second] as $variant) {
            DB::table('stock_balances')->insert([
                'warehouse_id' => $warehouseId, 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10.0000', 'reserved_qty' => '2.0000',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$first, $second];
    }

    private function merchantActor(): UserAccount
    {
        $actor = UserAccount::factory()->create();
        $permission = PermissionDefinition::query()->where('code', 'merchant.manage')->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('system')->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Merchant test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);

        return $actor;
    }
}
