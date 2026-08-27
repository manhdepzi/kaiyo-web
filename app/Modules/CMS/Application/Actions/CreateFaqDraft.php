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
use Illuminate\Support\Str;

final readonly class CreateFaqDraft
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @return array{faq:Faq,revision:FaqRevision} */
    public function execute(UserAccount $actor, string $code, string $question, string $answerMarkdown, int $position = 0): array
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $code = Str::slug($code);
        $question = trim($question);
        $answerMarkdown = trim($answerMarkdown);
        if ($code === '' || mb_strlen($code) > 180 || $question === '' || mb_strlen($question) > 500 || $answerMarkdown === '' || $position < 0) {
            throw new DomainException('FAQ code, question, answer and position are invalid.');
        }

        return DB::transaction(function () use ($actor, $code, $question, $answerMarkdown, $position): array {
            $faq = Faq::query()->create(['code' => $code, 'status' => 'draft']);
            $revision = FaqRevision::query()->create([
                'faq_id' => $faq->getKey(),
                'revision_no' => 1,
                'question' => $question,
                'answer_markdown' => $answerMarkdown,
                'position' => $position,
                'integrity_hash' => hash('sha256', json_encode([$code, 1, $question, $answerMarkdown, $position], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $faq->forceFill(['current_revision_id' => $revision->getKey()])->save();

            return ['faq' => $faq->refresh(), 'revision' => $revision];
        }, 3);
    }
}
