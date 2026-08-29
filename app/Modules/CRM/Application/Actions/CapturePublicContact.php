<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Data\PublicContactCommand;
use App\Modules\CRM\Application\Services\PublicContactAbuseGuard;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\CRM\Infrastructure\Persistence\Models\PublicContactSubmission;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CapturePublicContact
{
    /** @var list<string> */
    private const TOPICS = ['product', 'quotation', 'project', 'support', 'other'];

    public function __construct(
        private CrmIdentityNormalizer $normalizer,
        private PublicContactAbuseGuard $abuseGuard,
    ) {}

    public function execute(PublicContactCommand $command): Lead
    {
        $name = trim($command->name);
        $company = $this->optional($command->companyName);
        $email = $this->optional($command->email);
        $phone = $this->optional($command->phone);
        $message = trim($command->message);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 200 || ($email === null && $phone === null)
            || ! in_array($command->topic, self::TOPICS, true)
            || mb_strlen($message) < 20 || mb_strlen($message) > 4000
            || trim($command->operationKey) === '' || mb_strlen($command->operationKey) > 100) {
            throw new DomainException('Public contact input is invalid.');
        }

        $emailNormalized = $email === null ? null : $this->normalizer->email($email);
        $phoneNormalized = $phone === null ? null : $this->normalizer->phone($this->normalizeVietnamesePhone($phone));
        $operationHash = $this->normalizer->hash('public_contact_operation', trim($command->operationKey));
        $existing = $this->existing($operationHash);
        if ($existing !== null) {
            return $existing;
        }

        $this->abuseGuard->check($command->abuseKey);
        $abuseHash = $this->normalizer->hash('public_contact_abuse', $command->abuseKey);

        try {
            return DB::transaction(function () use ($name, $company, $email, $emailNormalized, $phone, $phoneNormalized, $command, $message, $operationHash, $abuseHash): Lead {
                $lead = Lead::query()->create([
                    'source' => 'public_contact',
                    'display_name' => $name,
                    'name_normalized' => $this->normalizer->name($name),
                    'company_name' => $company,
                    'email_display' => $email,
                    'email_normalized' => $emailNormalized,
                    'phone_display' => $phone,
                    'phone_e164' => $phoneNormalized,
                    'status' => 'new',
                    'owner_user_account_id' => null,
                    'sales_team_id' => null,
                ]);
                PublicContactSubmission::query()->create([
                    'lead_id' => $lead->getKey(),
                    'topic' => $command->topic,
                    'message' => $message,
                    'operation_key_hash' => $operationHash,
                    'abuse_key_hash' => $abuseHash,
                    'privacy_accepted_at' => now(),
                    'submitted_at' => now(),
                ]);

                return $lead;
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existing($operationHash);
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existing(string $operationHash): ?Lead
    {
        return Lead::query()->whereHas('publicContactSubmission', fn ($query) => $query->where('operation_key_hash', $operationHash))->first();
    }

    private function normalizeVietnamesePhone(string $phone): string
    {
        $compact = preg_replace('/[\s().-]+/', '', trim($phone)) ?? '';

        return str_starts_with($compact, '0') ? '+84'.substr($compact, 1) : $compact;
    }

    private function optional(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
