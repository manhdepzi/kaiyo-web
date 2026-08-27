<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property string $operation_key @property int|null $page_id @property int|null $page_revision_id @property int|null $article_id @property int|null $article_revision_id @property int|null $faq_id @property int|null $faq_revision_id @property int|null $banner_id @property int|null $banner_revision_id @property string $action @property \Carbon\CarbonImmutable $due_at @property string $state @property int $expected_page_version @property int $attempts @property string|null $last_error_code @property int $created_by_user_account_id @property \Carbon\CarbonImmutable|null $completed_at */
final class PublicationSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expected_page_version' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function dueAt(): CarbonImmutable
    {
        $value = $this->getAttribute('due_at');
        if (! $value instanceof CarbonImmutable) {
            throw new LogicException('Publication schedule due_at cast is invalid.');
        }

        return $value;
    }
}
