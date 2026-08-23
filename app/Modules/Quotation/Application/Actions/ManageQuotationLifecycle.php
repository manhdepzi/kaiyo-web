<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Actions;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Quotation\Domain\QuotationApprovalPolicy;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ManageQuotationLifecycle
{
    public function __construct(private PermissionAuthorizer $authorizer, private QuotationApprovalPolicy $approval) {}

    public function submitGuest(QuoteRevision $revision, string $guestToken, string $operationKey, int $expectedVersion): QuoteRevision
    {
        $this->verifyGuest($revision, $guestToken);

        return $this->transition($revision, 'submit', 'submitted', $operationKey, $expectedVersion, ['identity' => bin2hex($this->tokenEvidence($guestToken))]);
    }

    public function submit(QuoteRevision $revision, string $operationKey, int $expectedVersion, UserAccount $actor): QuoteRevision
    {
        $this->authorize($revision, $actor, 'quotes.manage');

        return $this->transition($revision, 'submit', 'submitted', $operationKey, $expectedVersion, ['actor' => $actor->getKey()]);
    }

    public function submitCustomer(QuoteRevision $revision, string $operationKey, int $expectedVersion, UserAccount $actor): QuoteRevision
    {
        $evidence = $this->verifyCustomer($revision, $actor);

        return $this->transition($revision, 'submit', 'submitted', $operationKey, $expectedVersion, ['identity' => bin2hex($evidence)]);
    }

    public function process(QuoteRevision $revision, string $operationKey, int $expectedVersion, UserAccount $actor): QuoteRevision
    {
        $this->authorize($revision, $actor, 'quotes.manage');

        return $this->transition($revision, 'process', 'processing', $operationKey, $expectedVersion, ['actor' => $actor->getKey()]);
    }

    public function approve(QuoteRevision $revision, string $decisionKey, string $reason, UserAccount $actor): QuoteRevision
    {
        if ($revision->state !== 'processing' || trim($decisionKey) === '' || trim($reason) === '') {
            throw new DomainException('Quotation approval input or state is invalid.');
        }
        $permission = $this->approval->permission($revision->required_approval_tier);
        $this->authorize($revision, $actor, $permission);
        if ($revision->proposer_user_account_id !== null && $revision->proposer_user_account_id === $actor->getKey()) {
            throw new DomainException('Quotation proposer cannot self-approve an escalated revision.');
        }
        $existing = DB::table('quote_approvals')->where('decision_key', $decisionKey)->first();
        if ($existing !== null) {
            $data = get_object_vars($existing);
            if (($data['quote_revision_id'] ?? null) !== $revision->getKey() || ! hash_equals((string) ($data['proposal_hash'] ?? ''), $revision->integrity_hash)) {
                throw new DomainException('Quotation approval identity was reused with different evidence.');
            }

            return $revision->refresh();
        }
        DB::transaction(function () use ($revision, $decisionKey, $reason, $actor): void {
            $locked = QuoteRevision::query()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'processing' || ! hash_equals($locked->integrity_hash, $revision->integrity_hash)) {
                throw new DomainException('Quotation revision changed before approval.');
            }
            DB::table('quote_approvals')->insert([
                'quote_revision_id' => $locked->getKey(), 'tier' => $locked->required_approval_tier,
                'proposer_user_account_id' => $locked->proposer_user_account_id, 'approver_user_account_id' => $actor->getKey(),
                'decision' => 'approved', 'reason' => $reason, 'proposal_hash' => $locked->integrity_hash,
                'decision_key' => $decisionKey, 'decided_at' => now(),
            ]);
        }, 3);

        return $revision->refresh();
    }

    public function issue(QuoteRevision $revision, string $operationKey, int $expectedVersion, UserAccount $actor): QuoteRevision
    {
        $this->authorize($revision, $actor, 'quotes.issue');
        if ($revision->required_approval_tier !== 'sales') {
            $approved = DB::table('quote_approvals')->where('quote_revision_id', $revision->getKey())
                ->where('tier', $revision->required_approval_tier)->where('decision', 'approved')->where('proposal_hash', $revision->integrity_hash)->exists();
            if (! $approved) {
                throw new DomainException('Quotation requires current approval before issue.');
            }
        }

        return $this->transition($revision, 'issue', 'sent', $operationKey, $expectedVersion, ['actor' => $actor->getKey()]);
    }

    public function viewGuest(QuoteRevision $revision, string $guestToken, string $eventKey): QuoteRevision
    {
        $this->verifyGuest($revision, $guestToken);

        return $this->access($revision, $this->tokenEvidence($guestToken), 'viewed', $eventKey);
    }

    public function acceptGuest(QuoteRevision $revision, string $guestToken, string $eventKey): QuoteRevision
    {
        $this->verifyGuest($revision, $guestToken);

        return $this->access($revision, $this->tokenEvidence($guestToken), 'accepted', $eventKey);
    }

    public function rejectGuest(QuoteRevision $revision, string $guestToken, string $eventKey): QuoteRevision
    {
        $this->verifyGuest($revision, $guestToken);

        return $this->access($revision, $this->tokenEvidence($guestToken), 'rejected', $eventKey);
    }

    public function viewCustomer(QuoteRevision $revision, UserAccount $actor, string $eventKey): QuoteRevision
    {
        return $this->access($revision, $this->verifyCustomer($revision, $actor), 'viewed', $eventKey);
    }

    public function acceptCustomer(QuoteRevision $revision, UserAccount $actor, string $eventKey): QuoteRevision
    {
        return $this->access($revision, $this->verifyCustomer($revision, $actor), 'accepted', $eventKey);
    }

    public function rejectCustomer(QuoteRevision $revision, UserAccount $actor, string $eventKey): QuoteRevision
    {
        return $this->access($revision, $this->verifyCustomer($revision, $actor), 'rejected', $eventKey);
    }

    public function expire(QuoteRevision $revision, string $operationKey, int $expectedVersion, Carbon $effectiveAt): QuoteRevision
    {
        if ($revision->valid_until === null || $revision->valid_until->isAfter($effectiveAt)) {
            throw new DomainException('Quotation has not reached its immutable validity boundary.');
        }

        return $this->transition($revision, 'expire', 'expired', $operationKey, $expectedVersion, ['effective_at' => $effectiveAt->format(DATE_ATOM)]);
    }

    /** @param array<string, int|string> $evidence */
    private function transition(QuoteRevision $revision, string $action, string $target, string $key, int $expectedVersion, array $evidence): QuoteRevision
    {
        if (trim($key) === '' || strlen($key) > 100) {
            throw new DomainException('Quotation operation identity is invalid.');
        }
        $hash = hash('sha256', json_encode([$revision->getKey(), $action, $target, $evidence], JSON_THROW_ON_ERROR), true);
        $existing = $this->existingOperation($key, $hash);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($revision, $action, $target, $key, $expectedVersion, $hash): QuoteRevision {
            $locked = QuoteRevision::query()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
            $existing = $this->existingOperation($key, $hash, true);
            if ($existing !== null) {
                return $existing;
            }
            $allowed = match ($action) {
                'submit' => $locked->state === 'draft',
                'process' => $locked->state === 'submitted',
                'issue' => $locked->state === 'processing',
                'expire' => in_array($locked->state, ['sent', 'viewed'], true),
                default => false,
            };
            if (! $allowed || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Quotation transition is stale or illegal.');
            }
            $values = ['state' => $target, 'lock_version' => $expectedVersion + 1];
            $timestamp = ['submit' => 'submitted_at', 'process' => 'processing_at', 'issue' => 'sent_at', 'expire' => 'expired_at'][$action];
            $values[$timestamp] = now();
            if ($action === 'issue') {
                $values['valid_until'] = now()->addDays($locked->requested_validity_days);
            }
            $locked->forceFill($values)->save();
            DB::table('quote_operations')->insert([
                'operation_key' => $key, 'request_hash' => $hash, 'quote_revision_id' => $locked->getKey(),
                'action' => $action, 'result_state' => $target, 'result_version' => $expectedVersion + 1, 'created_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function access(QuoteRevision $revision, string $actorEvidence, string $target, string $eventKey): QuoteRevision
    {
        if (trim($eventKey) === '' || strlen($eventKey) > 100) {
            throw new DomainException('Quotation access identity is invalid.');
        }

        return DB::transaction(function () use ($revision, $actorEvidence, $target, $eventKey): QuoteRevision {
            $locked = QuoteRevision::query()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
            $existing = DB::table('quote_access_events')->where('event_key', $eventKey)->first();
            if ($existing !== null) {
                $data = get_object_vars($existing);
                if (($data['quote_revision_id'] ?? null) !== $locked->getKey()
                    || ($data['access_kind'] ?? null) !== $target
                    || ! is_string($data['actor_evidence_hash'] ?? null)
                    || ! hash_equals($data['actor_evidence_hash'], $actorEvidence)) {
                    throw new DomainException('Quotation access identity was reused with different evidence.');
                }

                return $locked;
            }
            if (! in_array($locked->state, ['sent', 'viewed'], true) || $locked->valid_until === null || $locked->valid_until->isPast()) {
                throw new DomainException('Quotation is not eligible for this customer action.');
            }
            if (! ($target === 'viewed' && $locked->state === 'viewed')) {
                $locked->forceFill(['state' => $target, $target.'_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
            }
            DB::table('quote_access_events')->insert([
                'quote_revision_id' => $locked->getKey(), 'event_key' => $eventKey, 'access_kind' => $target,
                'actor_evidence_hash' => $actorEvidence, 'occurred_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function verifyGuest(QuoteRevision $revision, string $token): void
    {
        $quote = Quote::query()->findOrFail($revision->quote_id);
        if ($quote->guest_access_hash === null || strlen($token) < 32 || ! hash_equals($quote->guest_access_hash, $this->tokenEvidence($token))) {
            throw new AuthorizationException('Quotation guest access denied.');
        }
    }

    private function verifyCustomer(QuoteRevision $revision, UserAccount $actor): string
    {
        $quote = Quote::query()->findOrFail($revision->quote_id);
        if ($quote->customer_id === null || ! Customer::query()->whereKey($quote->customer_id)->where('status', 'active')->where('user_account_id', $actor->getKey())->exists()) {
            throw new AuthorizationException('Quotation Customer access denied.');
        }

        return hash_hmac('sha256', 'customer:'.$quote->customer_id.':account:'.$actor->getKey(), (string) config('app.key'), true);
    }

    private function authorize(QuoteRevision $revision, UserAccount $actor, string $permission): void
    {
        $quote = Quote::query()->findOrFail($revision->quote_id);
        $scope = $quote->customer_id !== null ? AuthorizationScope::customer('quotes', $quote->customer_id) : AuthorizationScope::module('quotes');
        if (! $this->authorizer->allowsPersistent($actor, $permission, $scope)) {
            throw new AuthorizationException('Quotation permission denied.');
        }
    }

    private function tokenEvidence(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'), true);
    }

    private function existingOperation(string $key, string $hash, bool $lock = false): ?QuoteRevision
    {
        $query = DB::table('quote_operations')->where('operation_key', $key);
        $operation = $lock ? $query->lockForUpdate()->first() : $query->first();
        if ($operation === null) {
            return null;
        }
        $data = get_object_vars($operation);
        if (! isset($data['request_hash'], $data['quote_revision_id']) || ! is_string($data['request_hash']) || ! is_int($data['quote_revision_id'])) {
            throw new DomainException('Stored quotation operation evidence is invalid.');
        }
        if (! hash_equals($data['request_hash'], $hash)) {
            throw new DomainException('Quotation operation identity was reused with different evidence.');
        }

        return QuoteRevision::query()->findOrFail($data['quote_revision_id']);
    }
}
