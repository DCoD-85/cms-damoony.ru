<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class VarsExtractCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'vars:extract';
    }

    public function description(): string
    {
        return 'Перенести $available/$auth/$search/$default из .php в .data.json';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || !preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректные параметры');
            return $r;
        }

        $dry = isset($args['dry']);
        $clean = !isset($args['no-clean']);

        $base = $this->paths->algoritm($domain, $allias);
        if (!Fs::isDir($base)) {
            $r->error('Каталог не найден: ' . $base);
            return $r;
        }

        $files = $this->scan($base);
        $created = 0;
        $skipped = 0;
        $cleaned = 0;

        foreach ($files as $phpFile) {
            $rel = substr($phpFile, strlen($base) + 1);
            if (!str_ends_with($rel, '.php') || str_contains($rel, '.data.')) {
                continue;
            }
            if (basename($rel) === 'algoritm_pageshablon.php') {
                continue;
            }

            $dataFile = preg_replace('/\.php$/', '.data.json', $phpFile);
            if (!is_string($dataFile)) {
                continue;
            }

            if (Fs::isFile($dataFile)) {
                $skipped++;
                continue;
            }

            $content = @file_get_contents($phpFile);
            if ($content === false) {
                continue;
            }

            $available = $this->extractAvailable($content);
            $auth = $this->extractBool($content, 'auth', false);
            $search = $this->extractBool($content, 'search', true);
            $default = $this->extractBool($content, 'default', false);

            $data = [
                'available' => $available,
                'auth' => $auth,
                'search' => $search,
                'default' => $default,
            ];

            if ($dry) {
                $r->file($dataFile);
                $context->step('DRY: будет создан', true, $rel);
            } else {
                Fs::writeAtomic($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
                $r->file($dataFile);
                $context->step('Создан .data.json', true, $rel);
            }
            $created++;

            if ($clean) {
                $newContent = $this->cleanPhp($content);
                if ($newContent !== $content && !$dry) {
                    Fs::writeAtomic($phpFile, $newContent);
                    $context->step('Очищен .php', true, $rel);
                    $cleaned++;
                }
            }
        }

        $context->step(sprintf('Итого: создано %d, пропущено %d, очищено %d', $created, $skipped, $cleaned), true);

        return $r;
    }

    /** @return list<string> */
    private function scan(string $base): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);
        return $out;
    }

    /** @return list<string> */
    /** @return list<string> */
    private function extractAvailable(string $content): array
    {
        if (!preg_match('/public\s+array\s+\$available\s*=\s*\[(.*?)\];/s', $content, $m)) {
            return [''];
        }

        preg_match_all('/[\'"]([^\'"]*)[\'"]/', $m[1], $matches);
        /** @var list<string> $values */
        $values = $matches[1];
        return $values === [] ? [''] : $values;
    }

    private function extractBool(string $content, string $name, bool $default): bool
    {
        if (!preg_match('/public\s+bool\s+\$' . $name . '\s*=\s*(true|false)\s*;/', $content, $m)) {
            return $default;
        }
        return $m[1] === 'true';
    }

    private function cleanPhp(string $content): string
    {
        $patterns = [
            '/\n?\s*public\s+array\s+\$available\s*=\s*\[.*?\];/s',
            '/\n?\s*public\s+bool\s+\$auth\s*=\s*(true|false)\s*;/',
            '/\n?\s*public\s+bool\s+\$search\s*=\s*(true|false)\s*;/',
            '/\n?\s*public\s+bool\s+\$default\s*=\s*(true|false)\s*;/',
            '/\n?\s*public\s+bool\s+\$logged\s*=\s*(true|false)\s*;/',
        ];
        $new = preg_replace($patterns, '', $content);
        if ($new === null) {
            return $content;
        }
        $new = preg_replace("/\n{3,}/", "\n\n", $new);
        return $new ?? $content;
    }
}
