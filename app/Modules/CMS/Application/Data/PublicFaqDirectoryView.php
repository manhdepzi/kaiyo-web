<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class PublicFaqDirectoryView
{
    /** @param list<array{code:string,question:string,sanitized_answer_html:string}> $items */
    public function __construct(public array $items) {}
}
