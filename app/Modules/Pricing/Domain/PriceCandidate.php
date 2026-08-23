<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use DomainException;

final readonly class PriceCandidate
{
    public const LAYERS = ['base', 'promotion', 'b2b', 'override', 'quotation'];

    public function __construct(public string $layer, public int $priority, public int $unitAmount, public string $sourceReference, public bool $eligible = true, public bool $approved = true)
    {
        if (! in_array($layer, self::LAYERS, true) || $unitAmount < 0 || trim($sourceReference) === '') {
            throw new DomainException('Price candidate is invalid.');
        }
        if (in_array($layer, ['override', 'quotation'], true) && ! $approved) {
            throw new DomainException('Manual or quotation price requires approved authority.');
        }
    }
}
