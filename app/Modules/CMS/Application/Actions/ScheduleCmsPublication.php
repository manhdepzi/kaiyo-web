<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\CMS\Infrastructure\Persistence\Models\PublicationSchedule;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ScheduleCmsPublication
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Article|Faq|Banner $content, string $action, CarbonImmutable $dueAt, string $operationKey, int $expectedVersion): PublicationSchedule
    {
        if (! $this->authorizer->allows($actor, 'content.publish', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content publishing permission is required.');
        }
        if (! in_array($action, ['publish', 'unpublish'], true)) {
            throw new DomainException('Publication schedule action is invalid.');
        }
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{7,63}\z/', $operationKey) !== 1) {
            throw new DomainException('Publication operation key is invalid.');
        }
        $dueAt = $dueAt->startOfSecond();
        [$type, $ownerColumn, $revisionColumn] = $this->identity($content);

        return DB::transaction(function () use ($actor, $content, $action, $dueAt, $operationKey, $expectedVersion, $type, $ownerColumn, $revisionColumn): PublicationSchedule {
            $existing = PublicationSchedule::query()->where('operation_key', $operationKey)->first();
            if ($existing !== null) {
                if ((int) $existing->getAttribute($ownerColumn) !== (int) $content->getKey()
                    || $existing->action !== $action || ! $existing->dueAt()->equalTo($dueAt)
                    || $existing->expected_page_version !== $expectedVersion + 1) {
                    throw new DomainException('Publication operation key was reused with another payload.');
                }

                return $existing;
            }
            $locked = match ($type) {
                'article' => Article::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                'faq' => Faq::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                'banner' => Banner::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                default => throw new LogicException('Unsupported schedulable CMS type.'),
            };
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Content changed before scheduling.');
            }
            if ($action === 'publish' && $locked->current_revision_id === null) {
                throw new DomainException('A current revision is required for scheduled publication.');
            }
            if ($action === 'unpublish' && $locked->published_revision_id === null) {
                throw new DomainException('Only public content can be scheduled for unpublication.');
            }
            $newVersion = $locked->lock_version + 1;
            $schedule = PublicationSchedule::query()->create([
                'operation_key' => $operationKey,
                $ownerColumn => $locked->getKey(),
                $revisionColumn => $action === 'publish' ? $locked->current_revision_id : null,
                'action' => $action,
                'due_at' => $dueAt,
                'state' => 'pending',
                'expected_page_version' => $newVersion,
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill(['status' => 'scheduled', 'lock_version' => $newVersion])->save();

            return $schedule;
        }, 3);
    }

    /** @return array{string,string,string} */
    private function identity(Article|Faq|Banner $content): array
    {
        return match (true) {
            $content instanceof Article => ['article', 'article_id', 'article_revision_id'],
            $content instanceof Faq => ['faq', 'faq_id', 'faq_revision_id'],
            $content instanceof Banner => ['banner', 'banner_id', 'banner_revision_id'],
        };
    }
}
