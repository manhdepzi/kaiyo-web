<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $faq_id
 * @property int $revision_no
 * @property string $question
 * @property string $answer_markdown
 * @property int $position
 * @property string $integrity_hash
 * @property int $created_by_user_account_id
 * @property CarbonImmutable|null $published_at
 */
final class FaqRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'position' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
