<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $quote_id
 * @property int $revision_no
 * @property string $state
 * @property string $required_approval_tier
 * @property int $final_amount
 * @property int $requested_validity_days
 * @property array<string, string|null> $billing_address
 * @property array<string, string|null> $shipping_address
 * @property string $shipping_method
 * @property array<string, mixed> $shipping_preparation
 * @property array<string, mixed> $tax_calculation
 * @property string $payment_method
 * @property bool $invoice_requested
 * @property string $integrity_hash
 * @property int|null $proposer_user_account_id
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $sent_at
 * @property int $lock_version
 */
final class QuoteRevision extends Model
{
    protected $guarded = [];

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }

    protected function casts(): array
    {
        return [
            'commercial_terms' => 'array',
            'billing_address' => 'array', 'shipping_address' => 'array', 'shipping_preparation' => 'array', 'tax_calculation' => 'array',
            'invoice_requested' => 'boolean',
            'merchandise_amount' => 'integer', 'discount_amount' => 'integer', 'tax_amount' => 'integer',
            'shipping_amount' => 'integer', 'final_amount' => 'integer', 'requested_validity_days' => 'integer', 'lock_version' => 'integer',
            'valid_until' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'processing_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime', 'viewed_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime', 'expired_at' => 'immutable_datetime', 'converted_at' => 'immutable_datetime',
        ];
    }
}
