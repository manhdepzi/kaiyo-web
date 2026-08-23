<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\CrmIdentityKey;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\DuplicateReview;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use Carbon\CarbonInterface;
use DomainException;

final readonly class CrmPartyService
{
    public function __construct(private CrmIdentityNormalizer $normalizer) {}

    /** @param array{email?: array{value: string, verified_at: CarbonInterface}, phone?: array{value: string, verified_at: CarbonInterface}} $verifiedIdentities */
    public function createCustomer(string $displayName, ?string $email, ?string $phone, ?string $source, array $verifiedIdentities = []): Customer
    {
        $customer = Customer::query()->create([
            'display_name' => trim($displayName),
            'name_normalized' => $this->normalizer->name($displayName),
            'status' => 'active',
            'primary_email_display' => $email === null ? null : trim($email),
            'primary_email_normalized' => $email === null ? null : $this->normalizer->email($email),
            'primary_phone_display' => $phone === null ? null : trim($phone),
            'primary_phone_e164' => $phone === null ? null : $this->normalizer->phone($phone),
            'acquisition_source' => $source === null ? null : trim($source),
        ]);

        $this->registerVerifiedIdentities('customer', (int) $customer->getKey(), $verifiedIdentities);
        $this->openCustomerFuzzyReview($customer);

        return $customer->refresh();
    }

    /** @param array{tax_code?: array{value: string, verified_at: CarbonInterface}} $verifiedIdentities */
    public function createCompany(string $legalName, ?string $displayName, ?string $taxCode, ?string $source, array $verifiedIdentities = []): Company
    {
        $effectiveName = trim($displayName ?? $legalName);
        $company = Company::query()->create([
            'legal_name' => trim($legalName),
            'display_name' => $effectiveName,
            'name_normalized' => $this->normalizer->name($effectiveName),
            'tax_code_display' => $taxCode === null ? null : trim($taxCode),
            'tax_code_normalized' => $taxCode === null ? null : $this->normalizer->taxCode($taxCode),
            'status' => 'active',
            'acquisition_source' => $source === null ? null : trim($source),
        ]);

        $this->registerVerifiedIdentities('company', (int) $company->getKey(), $verifiedIdentities);
        $this->openCompanyFuzzyReview($company);

        return $company->refresh();
    }

    public function findExact(string $type, string $value): ?CrmIdentityKey
    {
        $normalized = match ($type) {
            'email' => $this->normalizer->email($value),
            'phone' => $this->normalizer->phone($value),
            'tax_code' => $this->normalizer->taxCode($value),
            default => throw new DomainException('Unsupported CRM identity type.'),
        };

        return CrmIdentityKey::query()
            ->where('key_type', $type)
            ->where('normalized_hash', $this->normalizer->hash($type, $normalized))
            ->where('active', true)
            ->whereNotNull('verified_at')
            ->first();
    }

    /** @param array<string, array{value: string, verified_at: CarbonInterface}> $identities */
    private function registerVerifiedIdentities(string $subjectType, int $subjectId, array $identities): void
    {
        foreach ($identities as $type => $evidence) {
            $normalized = match ($type) {
                'email' => $this->normalizer->email($evidence['value']),
                'phone' => $this->normalizer->phone($evidence['value']),
                'tax_code' => $this->normalizer->taxCode($evidence['value']),
                default => throw new DomainException('Unsupported verified CRM identity.'),
            };
            $hash = $this->normalizer->hash($type, $normalized);
            if (CrmIdentityKey::query()->where('key_type', $type)->where('normalized_hash', $hash)->where('active', true)->exists()) {
                throw new DomainException('An active party already owns this verified identity.');
            }
            CrmIdentityKey::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'key_type' => $type,
                'normalized_hash' => $hash,
                'verified_at' => $evidence['verified_at'],
                'active' => true,
            ]);
        }
    }

    private function openCustomerFuzzyReview(Customer $candidate): void
    {
        $target = Customer::query()->whereKeyNot($candidate->getKey())->whereIn('status', ['active', 'duplicate_review'])
            ->get(['id', 'name_normalized'])->first(fn (Customer $item): bool => $this->similar($candidate->name_normalized, $item->name_normalized));
        if ($target === null) {
            return;
        }
        $this->createReview('customer', (int) $candidate->getKey(), (int) $target->getKey(), $candidate->name_normalized, $target->name_normalized);
        $candidate->forceFill(['status' => 'duplicate_review'])->save();
    }

    private function openCompanyFuzzyReview(Company $candidate): void
    {
        $target = Company::query()->whereKeyNot($candidate->getKey())->whereIn('status', ['active', 'duplicate_review'])
            ->get(['id', 'name_normalized'])->first(fn (Company $item): bool => $this->similar($candidate->name_normalized, $item->name_normalized));
        if ($target === null) {
            return;
        }
        $this->createReview('company', (int) $candidate->getKey(), (int) $target->getKey(), $candidate->name_normalized, $target->name_normalized);
        $candidate->forceFill(['status' => 'duplicate_review'])->save();
    }

    private function similar(string $left, string $right): bool
    {
        $length = max(strlen($left), strlen($right));
        if ($length === 0) {
            return false;
        }

        return 1 - (levenshtein($left, $right) / $length) >= (float) config('crm.fuzzy_name_threshold', 0.86);
    }

    private function createReview(string $type, int $candidateId, int $targetId, string $candidateName, string $targetName): void
    {
        $pair = [$type, min($candidateId, $targetId), max($candidateId, $targetId)];
        $attributes = [
            'candidate_customer_id' => $type === 'customer' ? $candidateId : null,
            'candidate_company_id' => $type === 'company' ? $candidateId : null,
            'target_customer_id' => $type === 'customer' ? $targetId : null,
            'target_company_id' => $type === 'company' ? $targetId : null,
            'match_kind' => 'fuzzy_name',
            'evidence_redacted' => [
                'candidate_fingerprint' => substr(hash('sha256', $candidateName), 0, 12),
                'target_fingerprint' => substr(hash('sha256', $targetName), 0, 12),
            ],
            'pair_hash' => hash('sha256', json_encode($pair, JSON_THROW_ON_ERROR), true),
            'status' => 'open',
        ];
        DuplicateReview::query()->firstOrCreate(['pair_hash' => $attributes['pair_hash'], 'status' => 'open'], $attributes);
    }
}
