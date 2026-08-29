<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class DispatchOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_fact_is_atomic_idempotent_and_conflicting_identity_fails_closed(): void
    {
        $fact = $this->fact('order-001');
        DB::beginTransaction();
        app(StoreDispatchFact::class)->record($fact);
        DB::rollBack();
        self::assertSame(0, DB::table('dispatch_records')->count());

        app(StoreDispatchFact::class)->record($fact);
        app(StoreDispatchFact::class)->record($fact);
        self::assertSame(1, DB::table('dispatch_records')->count());
        self::assertSame('pending', DB::table('dispatch_records')->value('state'));

        $this->expectException(DomainException::class);
        app(StoreDispatchFact::class)->record(new DispatchFact(
            $fact->identity,
            $fact->type,
            $fact->version,
            $fact->aggregateType,
            $fact->aggregatePublicId,
            [...$fact->payload, 'source' => 'conflict'],
        ));
    }

    public function test_relay_publishes_committed_fact_once_and_persists_evidence(): void
    {
        Event::fake([DispatchFactReleased::class]);
        app(StoreDispatchFact::class)->record($this->fact('order-002'));

        $first = app(RelayDispatchRecords::class)->execute(10);
        $second = app(RelayDispatchRecords::class)->execute(10);

        self::assertSame(['published' => 1, 'failed' => 0], $first);
        self::assertSame(['published' => 0, 'failed' => 0], $second);
        Event::assertDispatchedTimes(DispatchFactReleased::class, 1);
        Event::assertDispatched(DispatchFactReleased::class, fn (DispatchFactReleased $event): bool => $event->type === 'commerce.order.placed'
            && $event->version === 1
            && $event->aggregatePublicId === 'order-002'
            && $event->payload['reservation_public_id'] === 'reservation-order-002');
        $record = DB::table('dispatch_records')->firstOrFail();
        self::assertSame('published', $record->state);
        self::assertSame(1, $record->attempt_count);
        self::assertNotNull($record->published_at);
    }

    public function test_consumer_failure_retries_same_record_and_dead_letters_at_limit(): void
    {
        config()->set('outbox.retry_base_seconds', 1);
        config()->set('outbox.max_attempts', 2);
        $calls = 0;
        Event::listen(DispatchFactReleased::class, function () use (&$calls): void {
            $calls++;
            throw new RuntimeException('Simulated consumer outage.');
        });
        app(StoreDispatchFact::class)->record($this->fact('order-003'));

        self::assertSame(['published' => 0, 'failed' => 1], app(RelayDispatchRecords::class)->execute(10));
        $first = DB::table('dispatch_records')->firstOrFail();
        self::assertSame('pending', $first->state);
        self::assertSame(1, $first->attempt_count);
        self::assertSame('runtimeexception', $first->last_error_code);

        $this->travel(2)->seconds();
        self::assertSame(['published' => 0, 'failed' => 1], app(RelayDispatchRecords::class)->execute(10));
        $dead = DB::table('dispatch_records')->firstOrFail();
        self::assertSame('dead', $dead->state);
        self::assertSame(2, $dead->attempt_count);
        self::assertSame(2, $calls);
    }

    public function test_admin_monitor_is_private_high_permission_gated_and_hides_payload(): void
    {
        app(StoreDispatchFact::class)->record($this->fact('order-004'));
        DB::table('dispatch_records')->update([
            'state' => 'dead', 'attempt_count' => 8, 'last_error_code' => 'provideroutage', 'updated_at' => now(),
        ]);
        $this->actingAs(UserAccount::factory()->create())->get(route('admin.outbox'))->assertForbidden();

        $admin = $this->outboxAdmin();
        $this->actingAs($admin)->get(route('admin.outbox'))->assertRedirect(route('account.security'));
        $admin->forceFill([
            'two_factor_secret' => encrypt('outbox-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['outbox-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ])->save();

        $this->actingAs($admin)->get(route('admin.outbox', ['state' => 'dead', 'event_type' => 'commerce.order.placed']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Transactional outbox')
            ->assertSee('commerce.order.placed')
            ->assertSee('provideroutage')
            ->assertDontSee('reservation-order-004')
            ->assertDontSee('payload_hash');
    }

    public function test_status_command_is_read_only_safe_and_only_enforces_explicit_gates(): void
    {
        app(StoreDispatchFact::class)->record($this->fact('order-status-pending'));
        app(StoreDispatchFact::class)->record($this->fact('order-status-publishing'));
        app(StoreDispatchFact::class)->record($this->fact('order-status-dead'));
        DB::table('dispatch_records')->where('aggregate_public_id', 'order-status-publishing')->update([
            'state' => 'publishing',
            'claimed_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ]);
        DB::table('dispatch_records')->where('aggregate_public_id', 'order-status-dead')->update([
            'state' => 'dead',
            'last_error_code' => 'secret-provider-detail',
            'updated_at' => now(),
        ]);
        DB::table('dispatch_records')->where('aggregate_public_id', 'order-status-pending')->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ]);

        self::assertSame(0, Artisan::call('outbox:status', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringNotContainsString('secret-provider-detail', $output);
        self::assertStringNotContainsString('reservation-order-status', $output);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($decoded['healthy']);
        self::assertSame(1, $decoded['counts']['pending']);
        self::assertSame(1, $decoded['counts']['publishing']);
        self::assertSame(1, $decoded['counts']['dead']);

        self::assertSame(1, Artisan::call('outbox:status', [
            '--json' => true,
            '--max-pending-age' => '60',
            '--max-publishing-age' => '60',
            '--fail-on-dead' => true,
        ]));
        /** @var array<string, mixed> $gated */
        $gated = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($gated['healthy']);
        self::assertCount(3, $gated['violations']);
        self::assertSame(3, DB::table('dispatch_records')->count());
    }

    public function test_status_command_rejects_invalid_threshold_without_querying_or_mutating(): void
    {
        self::assertSame(2, Artisan::call('outbox:status', ['--max-pending-age' => '-1']));
        self::assertSame(0, DB::table('dispatch_records')->count());
    }

    public function test_concurrency_probe_refuses_non_isolated_database_without_mutation(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->markTestSkipped('The committed multi-process probe runs after the transactional MySQL suite.');
        }

        self::assertSame(1, Artisan::call('outbox:concurrency-probe'));
        self::assertSame(0, DB::table('dispatch_records')->count());
    }

    private function fact(string $orderPublicId): DispatchFact
    {
        return new DispatchFact(
            'commerce.order.placed:v1:'.$orderPublicId,
            'commerce.order.placed',
            1,
            'order',
            $orderPublicId,
            [
                'order_public_id' => $orderPublicId,
                'reservation_public_id' => 'reservation-'.$orderPublicId,
                'source' => 'checkout',
            ],
        );
    }

    private function outboxAdmin(): UserAccount
    {
        $admin = UserAccount::factory()->create();
        $permission = PermissionDefinition::query()->where('code', 'system.audit.read')->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $admin->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('system')->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $admin->getKey(),
            'reason' => 'Outbox monitor test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);

        return $admin;
    }
}
