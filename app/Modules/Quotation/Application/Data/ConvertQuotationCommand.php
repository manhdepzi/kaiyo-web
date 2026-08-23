<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Data;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;

final readonly class ConvertQuotationCommand
{
    public function __construct(
        public QuoteRevision $revision,
        public int $customerId,
        public string $operationKey,
        public UserAccount $actor,
    ) {
        if ($customerId <= 0 || trim($operationKey) === '' || strlen($operationKey) > 90) {
            throw new DomainException('Quote conversion identity is invalid.');
        }
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'revision' => $this->revision->getKey(), 'integrity' => bin2hex($this->revision->integrity_hash),
            'customer' => $this->customerId, 'actor' => $this->actor->public_id,
        ], JSON_THROW_ON_ERROR), true);
    }
}
