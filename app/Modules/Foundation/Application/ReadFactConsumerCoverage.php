<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\FactConsumerCoverage;
use DomainException;

final readonly class ReadFactConsumerCoverage
{
    /**
     * Consumer labels are operational IDs only. They intentionally reveal no payload,
     * aggregate identity or external provider configuration.
     *
     * @var array<string, array{owner:string,consumers:list<string>}>
     */
    private const COVERAGE = [
        'catalog.projection.changed' => [
            'owner' => 'Catalog',
            'consumers' => ['search.cache.invalidate', 'merchant.refresh.intent'],
        ],
        'commerce.order.placed' => [
            'owner' => 'Checkout/Order',
            'consumers' => [],
        ],
        'commerce.order.state.changed' => [
            'owner' => 'Order',
            'consumers' => ['notification.order.in_app'],
        ],
        'inventory.availability.changed' => [
            'owner' => 'Inventory',
            'consumers' => ['merchant.refresh.intent'],
        ],
        'payment.verified' => [
            'owner' => 'Payment',
            'consumers' => ['order.confirmation_or_reconciliation'],
        ],
        'quotation.revision.state.changed' => [
            'owner' => 'Quotation',
            'consumers' => ['notification.quotation.in_app'],
        ],
        'shipping.shipment.state.changed' => [
            'owner' => 'Shipping',
            'consumers' => ['notification.shipment.in_app'],
        ],
    ];

    public function __construct(private DispatchFactCatalog $catalog) {}

    /** @return list<FactConsumerCoverage> */
    public function execute(): array
    {
        $known = $this->catalog->factTypes();
        if (array_keys(self::COVERAGE) !== $known) {
            throw new DomainException('Fact consumer coverage must define exactly every approved internal fact.');
        }

        return array_map(static fn (string $factType): FactConsumerCoverage => new FactConsumerCoverage(
            $factType,
            self::COVERAGE[$factType]['owner'],
            self::COVERAGE[$factType]['consumers'],
        ), $known);
    }
}
