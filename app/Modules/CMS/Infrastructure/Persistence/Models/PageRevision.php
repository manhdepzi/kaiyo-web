<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $page_id @property int $revision_no @property string $title @property string|null $summary @property string $body_markdown @property string $integrity_hash @property int $created_by_user_account_id @property \Carbon\CarbonImmutable|null $published_at */
final class PageRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
