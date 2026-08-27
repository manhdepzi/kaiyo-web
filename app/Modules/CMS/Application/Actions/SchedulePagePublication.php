<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PublicationSchedule;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SchedulePagePublication
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(
        UserAccount $actor,
        Page $page,
        string $action,
        CarbonImmutable $dueAt,
        string $operationKey,
        int $expectedVersion,
    ): PublicationSchedule {
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

        return DB::transaction(function () use ($actor, $page, $action, $dueAt, $operationKey, $expectedVersion): PublicationSchedule {
            $existing = PublicationSchedule::query()->where('operation_key', $operationKey)->first();
            if ($existing !== null) {
                if ($existing->page_id !== $page->getKey() || $existing->action !== $action || ! $existing->dueAt()->equalTo($dueAt)
                    || $existing->expected_page_version !== $expectedVersion + 1) {
                    throw new DomainException('Publication operation key was reused with another payload.');
                }

                return $existing;
            }
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Page changed before scheduling.');
            }
            if ($action === 'publish' && $locked->current_revision_id === null) {
                throw new DomainException('A current page revision is required for scheduled publication.');
            }
            if ($action === 'unpublish' && $locked->published_revision_id === null) {
                throw new DomainException('Only a public page can be scheduled for unpublication.');
            }
            $newVersion = $locked->lock_version + 1;
            $schedule = PublicationSchedule::query()->create([
                'operation_key' => $operationKey,
                'page_id' => $locked->getKey(),
                'page_revision_id' => $action === 'publish' ? $locked->current_revision_id : null,
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
}
