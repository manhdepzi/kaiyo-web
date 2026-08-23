<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $source
 * @property string $display_name
 * @property string|null $company_name
 * @property string|null $email_display
 * @property string|null $email_normalized
 * @property string|null $phone_display
 * @property string|null $phone_e164
 * @property string|null $tax_code_display
 * @property string|null $tax_code_normalized
 * @property string $status
 * @property int|null $owner_user_account_id
 * @property int|null $sales_team_id
 * @property int|null $converted_customer_id
 * @property int|null $converted_company_id
 * @property string|null $conversion_key_hash
 * @property int $lock_version
 */
final class Lead extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['converted_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
