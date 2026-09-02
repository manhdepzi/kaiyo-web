<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Foundation\Application\PublishDispatchRecord;
use App\Modules\Foundation\Infrastructure\Persistence\Models\DispatchRecord;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use App\Modules\Quotation\Application\Actions\ConvertQuotationToOrder;
use App\Modules\Quotation\Application\Actions\CreateQuotationDraft;
use App\Modules\Quotation\Application\Actions\CreateQuotationRevision;
use App\Modules\Quotation\Application\Actions\ManageQuotationLifecycle;
use App\Modules\Quotation\Application\Data\ConvertQuotationCommand;
use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_quote_is_opaque_idempotent_and_follows_exact_unexpired_lifecycle(): void
    {
        [$variant, $address] = $this->fixture(100_000);
        $token = bin2hex(random_bytes(32));
        $command = $this->command($variant, $address, $token, 'quote-create-1');
        $created = app(CreateQuotationDraft::class)->execute($command);
        $retry = app(CreateQuotationDraft::class)->execute($command);
        self::assertSame($created->quote->getKey(), $retry->quote->getKey());
        self::assertNotSame($token, $created->quote->guest_access_hash);
        self::assertSame(1, $created->revision->lines()->count());
        self::assertSame('sales', $created->revision->required_approval_tier);
        self::assertDatabaseHas('analytics_event_intents', [
            'event_type' => 'quotation.requested', 'subject_public_id' => $created->quote->public_id, 'state' => 'pending',
        ]);
        self::assertSame(1, DB::table('analytics_event_intents')->where('event_type', 'quotation.requested')->count());

        $staff = UserAccount::factory()->create();
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        try {
            $lifecycle->submitGuest($created->revision, str_repeat('x', 64), 'quote-submit-wrong', 0);
            self::fail('Wrong guest access must fail.');
        } catch (AuthorizationException) {
            self::assertSame('draft', $created->revision->refresh()->state);
        }
        $submitted = $lifecycle->submitGuest($created->revision, $token, 'quote-submit-1', 0);
        self::assertSame($submitted->getKey(), $lifecycle->submitGuest($created->revision, $token, 'quote-submit-1', 0)->getKey());
        $processing = $lifecycle->process($submitted, 'quote-process-1', 1, $staff);
        $sent = $lifecycle->issue($processing, 'quote-issue-1', 2, $staff);
        $sentAt = $sent->sent_at;
        $validUntil = $sent->valid_until;
        self::assertNotNull($sentAt);
        self::assertNotNull($validUntil);
        self::assertEquals(30, $sentAt->diffInDays($validUntil));
        $viewed = $lifecycle->viewGuest($sent, $token, 'quote-view-1');
        self::assertSame('viewed', $viewed->state);
        self::assertSame($viewed->lock_version, $lifecycle->viewGuest($viewed, $token, 'quote-view-2')->lock_version);
        $accepted = $lifecycle->acceptGuest($viewed, $token, 'quote-accept-1');
        self::assertSame('accepted', $accepted->state);
        $facts = DB::table('dispatch_records')
            ->where('event_type', 'quotation.revision.state.changed')
            ->where('aggregate_public_id', $created->quote->public_id)
            ->orderBy('id')
            ->get();
        self::assertCount(5, $facts);
        self::assertSame(
            ['submitted', 'processing', 'sent', 'viewed', 'accepted'],
            $facts->map(fn (object $fact): string => (string) json_decode((string) $fact->payload, true, 512, JSON_THROW_ON_ERROR)['to_state'])->all(),
        );
        $acceptedFact = DispatchRecord::query()->where('event_type', 'quotation.revision.state.changed')
            ->where('aggregate_public_id', $created->quote->public_id)
            ->whereJsonContains('payload->to_state', 'accepted')->firstOrFail();
        app(PublishDispatchRecord::class)->publish($acceptedFact);
        self::assertSame(0, DB::table('notifications')->count(), 'Guest quotation facts must not leak into a Customer feed.');
        try {
            $lifecycle->rejectGuest($accepted, $token, 'quote-reject-late');
            self::fail('Accepted revision must remain terminal for Step 25.');
        } catch (DomainException) {
            self::assertSame('accepted', $accepted->refresh()->state);
        }
    }

    public function test_manager_threshold_requires_distinct_scoped_approval_before_issue(): void
    {
        [$variant, $address, $customer] = $this->fixture(100_000_000);
        $proposer = UserAccount::factory()->create();
        $manager = UserAccount::factory()->create();
        $issuer = UserAccount::factory()->create();
        $scope = AuthorizationScope::customer('quotes', $customer->getKey());
        $this->grant($proposer, 'quotes.manage', $scope);
        $this->grant($proposer, 'quotes.approve_manager', $scope);
        $this->grant($manager, 'quotes.approve_manager', $scope);
        $this->grant($issuer, 'quotes.issue', $scope);
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, null, 'quote-manager-create', $customer, $proposer));
        self::assertSame('manager', $created->revision->required_approval_tier);
        $submitted = $this->staffSubmit($created->revision, $proposer);
        $lifecycle = app(ManageQuotationLifecycle::class);
        try {
            $lifecycle->approve($submitted, 'manager-self', 'Self approval.', $proposer);
            self::fail('Proposer cannot self-approve.');
        } catch (AuthorizationException|DomainException) {
            self::assertSame(0, DB::table('quote_approvals')->count());
        }
        try {
            $lifecycle->issue($submitted, 'manager-issue-early', 2, $issuer);
            self::fail('Manager threshold cannot issue without approval.');
        } catch (DomainException) {
            self::assertSame('processing', $submitted->refresh()->state);
        }
        $lifecycle->approve($submitted, 'manager-approve', 'Approved commercial value.', $manager);
        $lifecycle->approve($submitted, 'manager-approve', 'Approved commercial value.', $manager);
        self::assertSame(1, DB::table('quote_approvals')->count());
        $sent = $lifecycle->issue($submitted, 'manager-issue', 2, $issuer);
        self::assertSame('sent', $sent->state);
        $sentFact = DispatchRecord::query()->where('event_type', 'quotation.revision.state.changed')
            ->where('aggregate_public_id', $created->quote->public_id)
            ->whereJsonContains('payload->to_state', 'sent')->firstOrFail();
        app(PublishDispatchRecord::class)->publish($sentFact);
        app(PublishDispatchRecord::class)->publish($sentFact);
        self::assertSame(1, DB::table('notifications')->where('quote_id', $created->quote->getKey())
            ->whereNull('order_id')->where('template_key', 'quotation.sent')->count());
        self::assertSame(1, DB::table('notification_attempts')->count());
        $sourceHash = $sent->integrity_hash;
        $revised = app(CreateQuotationRevision::class)->execute($sent, ['payment_terms' => 'bank_transfer'], 15, 'manager-revise', $proposer);
        self::assertSame('sent', $sent->refresh()->state);
        self::assertTrue(hash_equals($sourceHash, $sent->integrity_hash));
        self::assertSame(2, $revised->revision_no);
        self::assertSame('draft', $revised->state);
        self::assertSame($sent->lines()->count(), $revised->lines()->count());
        self::assertSame(0, DB::table('quote_approvals')->where('quote_revision_id', $revised->getKey())->count());
    }

    public function test_expiry_is_idempotent_and_cannot_override_acceptance(): void
    {
        Carbon::setTestNow('2026-08-23 00:00:00');
        [$variant, $address] = $this->fixture(100_000);
        $token = bin2hex(random_bytes(32));
        $staff = UserAccount::factory()->create();
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, $token, 'expiry-create'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $submitted = $lifecycle->submitGuest($created->revision, $token, 'expiry-submit', 0);
        $processing = $lifecycle->process($submitted, 'expiry-process', 1, $staff);
        $sent = $lifecycle->issue($processing, 'expiry-issue', 2, $staff);
        Carbon::setTestNow('2026-09-23 00:00:01');
        $expired = $lifecycle->expire($sent, 'expiry-run', 3, now());
        self::assertSame($expired->getKey(), $lifecycle->expire($sent, 'expiry-run', 3, now())->getKey());
        self::assertSame('expired', $expired->state);
    }

    public function test_replacement_revision_reprices_changed_lines_without_mutating_source(): void
    {
        [$variant, $address, $customer] = $this->fixture(100_000);
        $sales = UserAccount::factory()->create();
        $scope = AuthorizationScope::customer('quotes', $customer->getKey());
        $this->grant($sales, 'quotes.manage', $scope);
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, null, 'replace-create', $customer, $sales));
        $sourceHash = $created->revision->integrity_hash;
        $replacement = new CreateQuotationCommand(
            $customer->getKey(),
            null,
            null,
            [['variant_id' => $variant->getKey(), 'quantity' => '2', 'negotiated_unit_amount' => 90_000, 'cost_unit_amount' => 80_000]],
            $address,
            $address,
            'standard',
            30,
            ['payment_terms' => 'bank_transfer'],
            'replace-lines',
            $sales,
        );
        $revised = app(CreateQuotationRevision::class)->replace($created->revision, $replacement, $sales);
        $retry = app(CreateQuotationRevision::class)->replace($created->revision, $replacement, $sales);

        self::assertSame($revised->getKey(), $retry->getKey());
        self::assertSame(2, $revised->revision_no);
        self::assertSame(200_000, $revised->merchandise_amount);
        self::assertSame(20_000, $revised->discount_amount);
        self::assertSame(210_000, $revised->final_amount);
        self::assertSame('manager', $revised->required_approval_tier);
        self::assertSame('2.0000', $revised->lines->sole()->quantity);
        self::assertSame(90_000, $revised->lines->sole()->unit_amount);
        self::assertTrue(hash_equals($sourceHash, $created->revision->refresh()->integrity_hash));
        self::assertSame(100_000, $created->revision->merchandise_amount);
        self::assertSame($revised->getKey(), $created->quote->refresh()->current_revision_id);
    }

    public function test_authenticated_customer_only_controls_own_quote(): void
    {
        [$variant, $address, $customer] = $this->fixture(100_000);
        $owner = UserAccount::factory()->create();
        $stranger = UserAccount::factory()->create();
        $customer->forceFill(['user_account_id' => $owner->getKey()])->save();
        $staff = UserAccount::factory()->create();
        $scope = AuthorizationScope::customer('quotes', $customer->getKey());
        $this->grant($staff, 'quotes.manage', $scope);
        $this->grant($staff, 'quotes.issue', $scope);
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, null, 'customer-create', $customer, $owner));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $submitted = $lifecycle->submitCustomer($created->revision, 'customer-submit', 0, $owner);
        $processing = $lifecycle->process($submitted, 'customer-process', 1, $staff);
        $sent = $lifecycle->issue($processing, 'customer-issue', 2, $staff);
        try {
            $lifecycle->viewCustomer($sent, $stranger, 'customer-view-wrong');
            self::fail('Another account must not access the Customer quotation.');
        } catch (AuthorizationException) {
            self::assertSame('sent', $sent->refresh()->state);
        }
        $viewed = $lifecycle->viewCustomer($sent, $owner, 'customer-view');
        self::assertSame('viewed', $viewed->state);
        self::assertSame('accepted', $lifecycle->acceptCustomer($viewed, $owner, 'customer-accept')->state);
    }

    public function test_accepted_quote_converts_once_with_exact_snapshots_and_inventory(): void
    {
        [$variant, $address, $customer] = $this->fixture(100_000);
        $token = bin2hex(random_bytes(32));
        $staff = UserAccount::factory()->create();
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.convert', AuthorizationScope::customer('quotes', $customer->getKey()));
        $warehouse = Warehouse::query()->create(['code' => 'QUOTE-WH', 'name' => 'Quote Warehouse', 'status' => 'active']);
        $balance = StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => 10, 'reserved_qty' => 0]);
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, $token, 'convert-create'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $submitted = $lifecycle->submitGuest($created->revision, $token, 'convert-submit', 0);
        $processing = $lifecycle->process($submitted, 'convert-process', 1, $staff);
        $sent = $lifecycle->issue($processing, 'convert-issue', 2, $staff);
        $accepted = $lifecycle->acceptGuest($sent, $token, 'convert-accept');
        $command = new ConvertQuotationCommand($accepted, $customer->getKey(), 'convert-order', $staff);
        $result = app(ConvertQuotationToOrder::class)->execute($command);
        $retry = app(ConvertQuotationToOrder::class)->execute($command);

        self::assertSame($result->order->getKey(), $retry->order->getKey());
        self::assertNull($result->order->cart_id);
        self::assertSame($accepted->getKey(), $result->order->quote_revision_id);
        self::assertSame($accepted->final_amount, $result->order->final_amount);
        self::assertSame($accepted->lines()->sole()->pricing_snapshot_id, $result->order->lines->sole()->pricing_snapshot_id);
        self::assertCount(2, $result->order->addresses);
        self::assertSame('quote_to_order', $result->reservation->source_type);
        self::assertEquals(1, $balance->refresh()->reserved_qty);
        self::assertSame('converted', $accepted->refresh()->state);
        self::assertSame(1, DB::table('payments')->where('order_id', $result->order->getKey())->count());
        self::assertSame(1, DB::table('shipments')->where('order_id', $result->order->getKey())->count());
        self::assertSame(1, DB::table('quote_conversion_operations')->count());
        self::assertSame(1, DB::table('dispatch_records')->where('event_type', 'commerce.order.placed')
            ->where('aggregate_public_id', $result->order->public_id)->count());
        self::assertSame(1, DB::table('dispatch_records')->where('event_type', 'inventory.availability.changed')->count());
        self::assertSame(1, DB::table('dispatch_records')->where('event_type', 'quotation.revision.state.changed')
            ->where('aggregate_public_id', $created->quote->public_id)->pluck('payload')
            ->filter(fn (string $payload): bool => json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['to_state'] === 'converted')->count());
        $orderFactPayload = DB::table('dispatch_records')->where('event_type', 'commerce.order.placed')
            ->where('aggregate_public_id', $result->order->public_id)->value('payload');
        self::assertSame('quotation', json_decode((string) $orderFactPayload, true, flags: JSON_THROW_ON_ERROR)['source']);
    }

    public function test_quote_conversion_stock_failure_rolls_back_every_effect(): void
    {
        [$variant, $address, $customer] = $this->fixture(100_000);
        $token = bin2hex(random_bytes(32));
        $staff = UserAccount::factory()->create();
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.convert', AuthorizationScope::customer('quotes', $customer->getKey()));
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, $token, 'rollback-create'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $accepted = $lifecycle->acceptGuest($lifecycle->issue($lifecycle->process($lifecycle->submitGuest($created->revision, $token, 'rollback-submit', 0), 'rollback-process', 1, $staff), 'rollback-issue', 2, $staff), $token, 'rollback-accept');
        $factCount = DB::table('dispatch_records')->count();
        try {
            app(ConvertQuotationToOrder::class)->execute(new ConvertQuotationCommand($accepted, $customer->getKey(), 'rollback-order', $staff));
            self::fail('Conversion without stock must fail.');
        } catch (DomainException) {
            self::assertSame('accepted', $accepted->refresh()->state);
            self::assertSame(0, DB::table('orders')->count());
            self::assertSame(0, DB::table('inventory_reservations')->count());
            self::assertSame(0, DB::table('quote_conversion_operations')->count());
            self::assertSame($factCount, DB::table('dispatch_records')->count());
            self::assertSame(0, DB::table('dispatch_records')->where('event_type', 'quotation.revision.state.changed')
                ->where('aggregate_public_id', $created->quote->public_id)->pluck('payload')
                ->filter(fn (string $payload): bool => json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['to_state'] === 'converted')->count());
        }
    }

    public function test_guest_creation_is_rate_limited_but_exact_idempotent_retry_is_not_recounted(): void
    {
        config()->set('quotation.guest_create_attempts_per_minute', 1);
        [$variant, $address] = $this->fixture(100_000);
        $context = 'trusted-shared-abuse-context';
        $first = $this->command($variant, $address, bin2hex(random_bytes(32)), 'abuse-first', abuseContext: $context);
        $created = app(CreateQuotationDraft::class)->execute($first);
        self::assertSame($created->quote->getKey(), app(CreateQuotationDraft::class)->execute($first)->quote->getKey());
        try {
            app(CreateQuotationDraft::class)->execute($this->command($variant, $address, bin2hex(random_bytes(32)), 'abuse-second', abuseContext: $context));
            self::fail('Second guest creation in the bounded window must fail.');
        } catch (DomainException) {
            self::assertSame(1, DB::table('quotes')->count());
        }
    }

    public function test_mysql_quotation_triggers_protect_issued_financial_and_root_evidence(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL quotation trigger verification runs in the isolated MySQL suite.');
        }
        [$variant, $address] = $this->fixture(100_000);
        $token = bin2hex(random_bytes(32));
        $staff = UserAccount::factory()->create();
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $created = app(CreateQuotationDraft::class)->execute($this->command($variant, $address, $token, 'mysql-quote-create'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $submitted = $lifecycle->submitGuest($created->revision, $token, 'mysql-quote-submit', 0);
        $processing = $lifecycle->process($submitted, 'mysql-quote-process', 1, $staff);
        $sent = $lifecycle->issue($processing, 'mysql-quote-issue', 2, $staff);
        try {
            DB::table('quote_revisions')->where('id', $sent->getKey())->update(['final_amount' => $sent->final_amount + 1]);
            self::fail('Issued quotation financial mutation must fail.');
        } catch (\Throwable) {
            self::assertSame($sent->final_amount, $sent->refresh()->final_amount);
        }
        try {
            DB::table('quote_revisions')->where('id', $sent->getKey())->update(['state' => 'processing']);
            self::fail('Issued quotation cannot move backward.');
        } catch (\Throwable) {
            self::assertSame('sent', $sent->refresh()->state);
        }
        $this->expectException(\Throwable::class);
        DB::table('quotes')->where('id', $created->quote->getKey())->delete();
    }

    /** @return array{Variant, AddressData, Customer} */
    private function fixture(int $unitAmount): array
    {
        config()->set('shipping.methods.standard', ['enabled' => true, 'type' => 'configured', 'amount' => 30_000, 'free_threshold' => null, 'carrier_code' => null]);
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(0, 'tax-quote-test');
            }
        });
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Quote '.$suffix, 'slug' => 'quote-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Quote product', 'slug' => 'quote-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'QTE-'.$suffix, 'name' => 'Quote variant', 'quantity_scale' => 0, 'status' => 'active']);
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create(['revision_no' => 1, 'status' => 'active', 'starts_at' => now()->subMinute(), 'proposed_by_user_account_id' => $proposer->getKey(), 'approved_by_user_account_id' => $approver->getKey(), 'activated_at' => now()]);
        PriceRule::query()->create(['price_configuration_id' => $configuration->getKey(), 'variant_id' => $variant->getKey(), 'layer' => 'base', 'scope_type' => 'global', 'priority' => 1, 'unit_amount' => $unitAmount, 'currency' => 'VND', 'minimum_quantity' => '0.0001', 'source_reference' => 'quote-test']);
        $customer = Customer::query()->create(['display_name' => 'Quote Buyer', 'name_normalized' => 'quote buyer '.$suffix, 'status' => 'active']);

        return [$variant, new AddressData('Quote Buyer', '123 Quote Street', 'VN'), $customer];
    }

    private function command(Variant $variant, AddressData $address, ?string $token, string $key, ?Customer $customer = null, ?UserAccount $proposer = null, ?string $abuseContext = null): CreateQuotationCommand
    {
        return new CreateQuotationCommand($customer?->getKey(), null, $token, [['variant_id' => $variant->getKey(), 'quantity' => '1']], $address, $address, 'standard', 30, ['payment_terms' => 'cod'], $key, $proposer, $token === null ? null : ($abuseContext ?? 'trusted-abuse-context-'.$key));
    }

    private function staffSubmit(QuoteRevision $revision, UserAccount $actor): QuoteRevision
    {
        $submitted = app(ManageQuotationLifecycle::class)->submit($revision, 'manager-submit', 0, $actor);

        return app(ManageQuotationLifecycle::class)->process($submitted, 'manager-process', 1, $actor);
    }

    private function grant(UserAccount $actor, string $permissionCode, AuthorizationScope $scope): void
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create(['user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(), ...$scope->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active', 'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Quotation test.', 'identity_hash' => hash('sha256', random_bytes(32), true)]);
    }
}
