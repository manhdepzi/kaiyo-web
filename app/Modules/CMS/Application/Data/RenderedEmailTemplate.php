<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class RenderedEmailTemplate
{
    public function __construct(public string $subject, public string $bodyHtml, public int $revision) {}
}
