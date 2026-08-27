<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Support;

use DomainException;

final class EmailTemplateSyntax
{
    private const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]{0,63})\s*\}\}/';

    /**
     * @param  list<string>  $allowedVariables
     * @return list<string>
     */
    public function validate(string $subject, string $bodyMarkdown, array $allowedVariables): array
    {
        $normalized = array_values(array_unique($allowedVariables));
        sort($normalized);
        foreach ($normalized as $variable) {
            if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $variable) !== 1) {
                throw new DomainException('Email template variable name is invalid.');
            }
        }
        $used = array_values(array_unique([...$this->extract($subject), ...$this->extract($bodyMarkdown)]));
        foreach ($used as $variable) {
            if (! in_array($variable, $normalized, true)) {
                throw new DomainException('Email template uses a variable outside its whitelist.');
            }
        }
        foreach ([$subject, $bodyMarkdown] as $content) {
            $withoutPlaceholders = preg_replace(self::PLACEHOLDER, '', $content);
            if ($withoutPlaceholders === null || str_contains($withoutPlaceholders, '{{') || str_contains($withoutPlaceholders, '}}') || str_contains($content, '{!!') || str_contains($content, '<?php') || str_contains($content, '@php') || str_contains($content, '@endphp')) {
                throw new DomainException('Email template contains unsupported executable syntax.');
            }
        }

        return $normalized;
    }

    /** @param array<string,string> $variables */
    public function render(string $content, array $variables, bool $escapeHtml): string
    {
        $rendered = preg_replace_callback(self::PLACEHOLDER, static function (array $match) use ($variables, $escapeHtml): string {
            $name = $match[1];
            if (! array_key_exists($name, $variables)) {
                throw new DomainException('A required email template variable is missing.');
            }
            $value = $variables[$name];

            return $escapeHtml ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $content);
        if ($rendered === null) {
            throw new DomainException('Email template rendering failed.');
        }

        return $rendered;
    }

    /** @return list<string> */
    public function extract(string $content): array
    {
        preg_match_all(self::PLACEHOLDER, $content, $matches);

        return array_map('strval', $matches[1]);
    }
}
