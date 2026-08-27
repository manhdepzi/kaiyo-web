<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\CMS\Infrastructure\Persistence\Models\FaqRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateFaqRevision
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Faq $faq, int $expectedVersion, string $question, string $answerMarkdown, int $position): FaqRevision
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $question = trim($question);
        $answerMarkdown = trim($answerMarkdown);
        if ($question === '' || mb_strlen($question) > 500 || $answerMarkdown === '' || $position < 0) {
            throw new DomainException('FAQ question, answer or position is invalid.');
        }

        return DB::transaction(function () use ($actor, $faq, $expectedVersion, $question, $answerMarkdown, $position): FaqRevision {
            $locked = Faq::query()->whereKey($faq->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('FAQ changed before revision creation.');
            }
            $revisionNo = ((int) FaqRevision::query()->where('faq_id', $locked->getKey())->max('revision_no')) + 1;
            $revision = FaqRevision::query()->create([
                'faq_id' => $locked->getKey(), 'revision_no' => $revisionNo, 'question' => $question,
                'answer_markdown' => $answerMarkdown, 'position' => $position,
                'integrity_hash' => hash('sha256', json_encode([$locked->code, $revisionNo, $question, $answerMarkdown, $position], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill(['current_revision_id' => $revision->getKey(), 'status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();

            return $revision;
        }, 3);
    }
}
