<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Services;

use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;

final readonly class QuotationStateFactRecorder
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['processing'],
        'processing' => ['sent'],
        'sent' => ['viewed', 'accepted', 'rejected', 'expired'],
        'viewed' => ['accepted', 'rejected', 'expired'],
        'accepted' => ['converted'],
    ];

    public function __construct(private StoreDispatchFact $dispatchFacts) {}

    public function record(QuoteRevision $revision, string $fromState): void
    {
        $toState = (string) $revision->state;
        $version = (int) $revision->lock_version;
        if (! in_array($toState, self::TRANSITIONS[$fromState] ?? [], true) || $version < 1) {
            throw new DomainException('Quotation state fact transition is invalid.');
        }
        $quote = Quote::query()->findOrFail($revision->quote_id);

        $this->dispatchFacts->record(new DispatchFact(
            identity: 'quotation.revision.state.changed:v1:'.$quote->public_id.':'.$revision->revision_no.':'.$version,
            type: 'quotation.revision.state.changed',
            version: 1,
            aggregateType: 'quote',
            aggregatePublicId: (string) $quote->public_id,
            payload: [
                'from_state' => $fromState,
                'revision_no' => (int) $revision->revision_no,
                'revision_version' => $version,
                'to_state' => $toState,
            ],
        ));
    }
}
