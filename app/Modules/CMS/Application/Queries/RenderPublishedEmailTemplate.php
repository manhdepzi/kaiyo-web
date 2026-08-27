<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\RenderedEmailTemplate;
use App\Modules\CMS\Application\Support\EmailTemplateSyntax;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplate;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplateRevision;
use DomainException;
use Illuminate\Support\Str;

final readonly class RenderPublishedEmailTemplate
{
    public function __construct(private EmailTemplateSyntax $syntax) {}

    /** @param array<string,string> $variables */
    public function render(string $templateKey, array $variables): RenderedEmailTemplate
    {
        $template = EmailTemplate::query()->where('template_key', $templateKey)->whereNotNull('published_revision_id')->first();
        if ($template === null || $template->published_revision_id === null) {
            throw new DomainException('Published email template was not found.');
        }
        $revision = EmailTemplateRevision::query()->whereKey($template->published_revision_id)->where('email_template_id', $template->getKey())->whereNotNull('published_at')->first();
        if ($revision === null) {
            throw new DomainException('Published email template revision was not found.');
        }
        foreach (array_keys($variables) as $name) {
            if (! in_array($name, $revision->allowed_variables, true)) {
                throw new DomainException('Email template received a variable outside its whitelist.');
            }
        }
        $subject = $this->syntax->render($revision->subject, $variables, false);
        if (str_contains($subject, "\r") || str_contains($subject, "\n")) {
            throw new DomainException('Rendered email subject contains a line break.');
        }
        $markdown = $this->syntax->render($revision->body_markdown, $variables, true);

        return new RenderedEmailTemplate(
            $subject,
            Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            $revision->revision_no,
        );
    }
}
