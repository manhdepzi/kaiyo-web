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

final readonly class CreateEmailTemplateDraft
{
    public function __construct(private PermissionAuthorizer $authorizer, private EmailTemplateSyntax $syntax) {}

    /**
     * @param  list<string>  $allowedVariables
     * @return array{template:EmailTemplate,revision:EmailTemplateRevision}
     */
    public function execute(UserAccount $actor, string $templateKey, string $subject, string $bodyMarkdown, array $allowedVariables): array
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $templateKey = trim($templateKey);
        $subject = trim($subject);
        $bodyMarkdown = trim($bodyMarkdown);
        if (preg_match('/\A[a-z][a-z0-9._-]{2,179}\z/', $templateKey) !== 1 || $subject === '' || mb_strlen($subject) > 500 || $bodyMarkdown === '') {
            throw new DomainException('Email template key, subject or body is invalid.');
        }
        $allowedVariables = $this->syntax->validate($subject, $bodyMarkdown, $allowedVariables);

        return DB::transaction(function () use ($actor, $templateKey, $subject, $bodyMarkdown, $allowedVariables): array {
            $template = EmailTemplate::query()->create(['template_key' => $templateKey, 'status' => 'draft']);
            $revision = EmailTemplateRevision::query()->create([
                'email_template_id' => $template->getKey(),
                'revision_no' => 1,
                'subject' => $subject,
                'body_markdown' => $bodyMarkdown,
                'allowed_variables' => $allowedVariables,
                'integrity_hash' => hash('sha256', json_encode([$templateKey, 1, $subject, $bodyMarkdown, $allowedVariables], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $template->forceFill(['current_revision_id' => $revision->getKey()])->save();

            return ['template' => $template->refresh(), 'revision' => $revision];
        }, 3);
    }
}
