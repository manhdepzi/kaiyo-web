<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Data\LeadConversionResult;
use App\Modules\CRM\Application\Services\CrmPartyService;
use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ConvertLead
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmPartyService $parties, private CrmIdentityNormalizer $normalizer) {}

    public function execute(UserAccount $actor, Lead $lead, string $idempotencyKey, bool $emailVerified = false, bool $phoneVerified = false, bool $taxVerified = false): LeadConversionResult
    {
        $scope = $lead->sales_team_id === null
            ? AuthorizationScope::owned('crm', (int) ($lead->owner_user_account_id ?? 0))
            : AuthorizationScope::salesTeam('crm', (int) $lead->sales_team_id);
        $this->authorize($this->authorizer, $actor, 'crm.leads.convert', $scope);
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 200) {
            throw new DomainException('Lead conversion idempotency key is invalid.');
        }
        $keyHash = $this->normalizer->hash('lead_conversion', $key);

        return DB::transaction(function () use ($lead, $keyHash, $emailVerified, $phoneVerified, $taxVerified): LeadConversionResult {
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === 'converted') {
                if (! is_string($locked->conversion_key_hash) || ! hash_equals($locked->conversion_key_hash, $keyHash)) {
                    throw new DomainException('The Lead was already converted by another command.');
                }

                return $this->result($locked);
            }
            if (! in_array($locked->status, ['new', 'qualified'], true)) {
                throw new DomainException('This Lead state cannot be converted.');
            }
            if (Lead::query()->where('conversion_key_hash', $keyHash)->whereKeyNot($locked->getKey())->exists()) {
                throw new DomainException('The conversion idempotency key is already in use.');
            }

            $customer = $this->exactCustomer($locked, $emailVerified, $phoneVerified);
            $company = $this->exactCompany($locked, $taxVerified);
            if ($customer === null) {
                $identities = [];
                if ($emailVerified && $locked->email_normalized !== null) {
                    $identities['email'] = ['value' => $locked->email_normalized, 'verified_at' => now()];
                }
                if ($phoneVerified && $locked->phone_e164 !== null) {
                    $identities['phone'] = ['value' => $locked->phone_e164, 'verified_at' => now()];
                }
                $customer = $this->parties->createCustomer($locked->display_name, $locked->email_display, $locked->phone_display, $locked->source, $identities);
            }
            if ($company === null && $locked->company_name !== null) {
                $identities = $taxVerified && $locked->tax_code_normalized !== null
                    ? ['tax_code' => ['value' => $locked->tax_code_normalized, 'verified_at' => now()]] : [];
                $company = $this->parties->createCompany($locked->company_name, null, $locked->tax_code_display, $locked->source, $identities);
            }

            $locked->forceFill([
                'status' => 'converted',
                'converted_customer_id' => $customer->getKey(),
                'converted_company_id' => $company?->getKey(),
                'converted_at' => now(),
                'conversion_key_hash' => $keyHash,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return new LeadConversionResult($locked->refresh(), $customer, $company);
        }, 3);
    }

    private function exactCustomer(Lead $lead, bool $emailVerified, bool $phoneVerified): ?Customer
    {
        $ids = [];
        if ($emailVerified && $lead->email_normalized !== null) {
            $identity = $this->parties->findExact('email', $lead->email_normalized);
            if ($identity?->subject_type === 'customer') {
                $ids[] = $identity->subject_id;
            }
        }
        if ($phoneVerified && $lead->phone_e164 !== null) {
            $identity = $this->parties->findExact('phone', $lead->phone_e164);
            if ($identity?->subject_type === 'customer') {
                $ids[] = $identity->subject_id;
            }
        }
        if (count(array_unique($ids)) > 1) {
            throw new DomainException('Verified Lead identities resolve to conflicting Customers.');
        }

        return $ids === [] ? null : Customer::query()->findOrFail($ids[0]);
    }

    private function exactCompany(Lead $lead, bool $taxVerified): ?Company
    {
        if (! $taxVerified || $lead->tax_code_normalized === null) {
            return null;
        }
        $identity = $this->parties->findExact('tax_code', $lead->tax_code_normalized);

        return $identity?->subject_type === 'company' ? Company::query()->findOrFail($identity->subject_id) : null;
    }

    private function result(Lead $lead): LeadConversionResult
    {
        return new LeadConversionResult(
            $lead,
            $lead->converted_customer_id === null ? null : Customer::query()->findOrFail($lead->converted_customer_id),
            $lead->converted_company_id === null ? null : Company::query()->findOrFail($lead->converted_company_id),
        );
    }
}
