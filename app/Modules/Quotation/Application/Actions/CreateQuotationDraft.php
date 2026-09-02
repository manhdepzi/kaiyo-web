<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Actions;

use App\Modules\CRM\Application\Services\CompanyCapabilityAuthorizer;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Growth\Application\StoreAnalyticsIntent;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use App\Modules\Quotation\Application\Data\QuotationDraftResult;
use App\Modules\Quotation\Application\Services\QuotationAbuseGuard;
use App\Modules\Quotation\Application\Services\QuotationRevisionBuilder;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateQuotationDraft
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private QuotationAbuseGuard $abuse,
        private CompanyCapabilityAuthorizer $companyCapabilities,
        private QuotationRevisionBuilder $revisions,
        private StoreAnalyticsIntent $analytics,
    ) {}

    public function execute(CreateQuotationCommand $command): QuotationDraftResult
    {
        $hash = $command->requestHash();
        $existing = $this->existing($command->operationKey, $hash);
        if ($existing !== null) {
            return $existing;
        }
        $this->abuse->check($command);

        try {
            return DB::transaction(function () use ($command, $hash): QuotationDraftResult {
                $existing = $this->existing($command->operationKey, $hash, true);
                if ($existing !== null) {
                    return $existing;
                }
                if ($command->customerId !== null) {
                    $customer = Customer::query()->whereKey($command->customerId)->where('status', 'active')->lockForUpdate()->first();
                    if ($customer === null) {
                        throw new DomainException('Quotation Customer is not active.');
                    }
                    if ($command->proposer === null) {
                        throw new AuthorizationException('Authenticated quotation requires an actor.');
                    }
                    $scope = AuthorizationScope::customer('quotes', $customer->getKey());
                    $ownsCustomer = $customer->user_account_id !== null && $customer->user_account_id === $command->proposer->getKey();
                    $staffAllowed = $this->authorizer->allowsPersistent($command->proposer, 'quotes.create', $scope)
                        || $this->authorizer->allowsPersistent($command->proposer, 'quotes.manage', $scope);
                    if (! $ownsCustomer && ! $staffAllowed) {
                        throw new AuthorizationException('Quotation Customer scope denied.');
                    }
                    if ($command->companyId !== null) {
                        $company = Company::query()->whereKey($command->companyId)->where('status', 'active')->lockForUpdate()->first();
                        if ($company === null) {
                            throw new DomainException('Quotation Company is not active.');
                        }
                        $companyScope = AuthorizationScope::company('quotes', $company->getKey());
                        $companyAllowed = $this->companyCapabilities->allows($command->proposer, $company, 'quotes.create')
                            || $this->authorizer->allowsPersistent($command->proposer, 'quotes.create', $companyScope)
                            || $this->authorizer->allowsPersistent($command->proposer, 'quotes.manage', $companyScope);
                        if (! $companyAllowed) {
                            throw new AuthorizationException('Quotation Company scope denied.');
                        }
                    }
                }
                $quote = Quote::query()->create([
                    'customer_id' => $command->customerId, 'company_id' => $command->companyId,
                    'guest_access_hash' => $command->guestAccessToken === null ? null : $this->guestHash($command->guestAccessToken),
                ]);
                $revision = $this->revisions->build($quote, $command, 1);
                $quote->forceFill(['current_revision_id' => $revision->getKey()])->save();
                DB::table('quote_operations')->insert([
                    'operation_key' => $command->operationKey, 'request_hash' => $hash, 'quote_revision_id' => $revision->getKey(),
                    'action' => 'create', 'result_state' => 'draft', 'result_version' => 0, 'created_at' => now(),
                ]);
                $this->analytics->record('quotation-requested:'.$quote->public_id, new AnalyticsEvent(
                    'quotation-requested:'.$quote->public_id,
                    'quotation.requested',
                    'quote',
                    $quote->public_id,
                    now()->toDateTimeImmutable(),
                    true,
                    ['line_count' => count($command->lines), 'source' => $command->guestAccessToken === null ? 'account' : 'guest'],
                    $command->analyticsConsentPublicId,
                ));

                return new QuotationDraftResult($quote->refresh(), $revision->load('lines'));
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existing($command->operationKey, $hash);
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existing(string $key, string $hash, bool $lock = false): ?QuotationDraftResult
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
            throw new DomainException('Quotation operation identity was reused with different input.');
        }
        $revision = QuoteRevision::query()->with('lines')->findOrFail($data['quote_revision_id']);

        return new QuotationDraftResult(Quote::query()->findOrFail($revision->quote_id), $revision);
    }

    private function guestHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'), true);
    }
}
