<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Application\Support\EmailTemplateSyntax;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplate;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplateRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateEmailTemplateRevision
{
    public function __construct(private PermissionAuthorizer $authorizer, private EmailTemplateSyntax $syntax) {}

    /** @param list<string> $allowedVariables */
    public function execute(UserAccount $actor, EmailTemplate $template, int $expectedVersion, string $subject, string $bodyMarkdown, array $allowedVariables): EmailTemplateRevision
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $subject = trim($subject);
        $bodyMarkdown = trim($bodyMarkdown);
        if ($subject === '' || mb_strlen($subject) > 500 || $bodyMarkdown === '') {
            throw new DomainException('Email template subject or body is invalid.');
        }
        $allowedVariables = $this->syntax->validate($subject, $bodyMarkdown, $allowedVariables);

        return DB::transaction(function () use ($actor, $template, $expectedVersion, $subject, $bodyMarkdown, $allowedVariables): EmailTemplateRevision {
            $locked = EmailTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Email template changed before revision creation.');
            }
            $revisionNo = ((int) EmailTemplateRevision::query()->where('email_template_id', $locked->getKey())->max('revision_no')) + 1;
            $revision = EmailTemplateRevision::query()->create([
                'email_template_id' => $locked->getKey(), 'revision_no' => $revisionNo, 'subject' => $subject,
                'body_markdown' => $bodyMarkdown, 'allowed_variables' => $allowedVariables,
                'integrity_hash' => hash('sha256', json_encode([$locked->template_key, $revisionNo, $subject, $bodyMarkdown, $allowedVariables], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill(['current_revision_id' => $revision->getKey(), 'status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();

            return $revision;
        }, 3);
    }
}
