<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $customer_id
 * @property string $label
 * @property string $recipient_name
 * @property string|null $company_name
 * @property string|null $tax_code
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string|null $locality
 * @property string|null $subdivision
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $phone
 * @property bool $is_default_shipping
 * @property bool $is_default_billing
 * @property string $status
 * @property int $lock_version
 */
final class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
