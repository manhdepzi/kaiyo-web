<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $email_template_id
 * @property int $revision_no
 * @property string $subject
 * @property string $body_markdown
 * @property list<string> $allowed_variables
 * @property string $integrity_hash
 * @property int $created_by_user_account_id
 * @property CarbonImmutable|null $published_at
 */
final class EmailTemplateRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'allowed_variables' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
