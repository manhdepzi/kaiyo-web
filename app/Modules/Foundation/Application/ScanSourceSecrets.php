<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\SourceSecretFinding;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ScanSourceSecrets
{
    /** @var array<string, string> */
    private const PATTERNS = [
        'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
        'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',
        'github_token' => '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/',
        'openai_style_key' => '/\bsk-[A-Za-z0-9_-]{20,}\b/',
        'hard_coded_credential' => '/\b(?:api[_-]?key|api[_-]?secret|access[_-]?token|client[_-]?secret|password)\s*[:=]\s*[\'\"][^\'\"${]{16,}[\'\"]/i',
    ];

    /** @var list<string> */
    private const EXTENSIONS = ['php', 'js', 'json', 'yml', 'yaml'];

    /**
     * @param  list<string>|null  $roots
     * @return list<SourceSecretFinding>
     */
    public function execute(?array $roots = null): array
    {
        $roots ??= [base_path('app'), base_path('config'), base_path('database'), base_path('routes'), base_path('.github'), base_path('composer.json'), base_path('package.json')];
        $findings = [];
        foreach ($roots as $root) {
            foreach ($this->files((string) $root) as $file) {
                $findings = [...$findings, ...$this->scanFile($file)];
            }
        }

        return $findings;
    }

    /** @return iterable<SplFileInfo> */
    private function files(string $root): iterable
    {
        if (is_file($root)) {
            yield new SplFileInfo($root);

            return;
        }
        if (! is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && in_array(mb_strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                yield $file;
            }
        }
    }

    /** @return list<SourceSecretFinding> */
    private function scanFile(SplFileInfo $file): array
    {
        $contents = file_get_contents($file->getPathname());
        if (! is_string($contents)) {
            return [];
        }

        $findings = [];
        foreach (self::PATTERNS as $category => $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) !== false) {
                foreach ($matches[0] as $match) {
                    $findings[] = new SourceSecretFinding(
                        str_replace('\\', '/', $file->getPathname()),
                        substr_count(substr($contents, 0, $match[1]), "\n") + 1,
                        $category,
                    );
                }
            }
        }

        return $findings;
    }
}
