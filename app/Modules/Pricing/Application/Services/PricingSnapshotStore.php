<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Pricing\Domain\PricingResult;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PricingCalculationSnapshot;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PricingSnapshotStore
{
    public function persist(PriceConfiguration $configuration, Variant $variant, PricingResult $result): PricingCalculationSnapshot
    {
        if ($configuration->status !== 'active') {
            throw new DomainException('Only an active pricing revision may produce a snapshot.');
        }
        $snapshot = $result->snapshot();
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), true);
        DB::table('pricing_calculation_snapshots')->insertOrIgnore([
            'snapshot_key' => (string) Str::ulid(),
            'price_configuration_id' => $configuration->getKey(),
            'variant_id' => $variant->getKey(),
            'quantity' => $result->quantity,
            'currency' => $result->currency,
            'unit_amount' => $result->unitAmount,
            'line_amount' => $result->lineAmount,
            'winning_layer' => $result->winningLayer,
            'source_reference' => $result->sourceReference,
            'rounding' => $result->rounding,
            'input_hash' => $hash,
            'resolution' => json_encode($result->resolution, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return PricingCalculationSnapshot::query()->where('price_configuration_id', $configuration->getKey())
            ->where('variant_id', $variant->getKey())->where('input_hash', $hash)->firstOrFail();
    }
}
