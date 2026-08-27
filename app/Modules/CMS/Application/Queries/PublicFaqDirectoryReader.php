<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\PublicFaqDirectoryView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PublicFaqDirectoryReader
{
    public function read(): PublicFaqDirectoryView
    {
        $rows = DB::table('faqs')->join('faq_revisions', 'faq_revisions.id', '=', 'faqs.published_revision_id')
            ->whereNull('faqs.deleted_at')->whereNotNull('faq_revisions.published_at')
            ->orderBy('faq_revisions.position')->orderBy('faqs.id')
            ->get(['faqs.code', 'faq_revisions.question', 'faq_revisions.answer_markdown']);
        $items = array_values($rows->map(static function (object $row): array {
            $values = get_object_vars($row);

            return [
                'code' => (string) $values['code'],
                'question' => (string) $values['question'],
                'sanitized_answer_html' => Str::markdown((string) $values['answer_markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            ];
        })->all());

        return new PublicFaqDirectoryView($items);
    }
}
