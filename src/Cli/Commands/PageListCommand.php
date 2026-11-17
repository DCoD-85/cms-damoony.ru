<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageRules;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * page:list для схемы domains_app.
 */
final class PageListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'page:list';
    }

    public function description(): string
    {
        return 'Список страниц алиаса (рекурсивно, с auth/search/default)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $algDir = $this->paths->algoritm($target->folder(), $target->aliasName());
        if (!is_dir($algDir)) {
            $r->error('Папка algoritm не найдена');

            return $r;
        }

        $rows = [];

        $rootPhp = $algDir . '/algoritm.php';
        if (is_file($rootPhp)) {
            $rows[] = $this->row('__root__', '/', $rootPhp, PageRules::load($algDir . '/algoritm.rules.json'));
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($algDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isDir()) {
                continue;
            }

            $dir = $file->getPathname();
            if ($dir === $algDir) {
                continue;
            }

            $rel = str_replace('\\', '/', trim(substr($dir, strlen($algDir)), '/\\'));
            if ($rel === '' || str_starts_with($rel, '.')) {
                continue;
            }

            $classChain = 'algoritm_' . str_replace('/', '_', $rel);
            $php = $dir . '/' . $classChain . '.php';
            if (!is_file($php)) {
                continue;
            }

            $rows[] = $this->row($rel, '/' . $rel, $php, PageRules::load($dir . '/' . $classChain . '.rules.json'));
        }

        foreach ($rows as $row) {
            $flags = [];
            if ($row['auth']) {
                $flags[] = 'auth';
            }
            if ($row['default']) {
                $flags[] = 'default';
            }
            if (!$row['search']) {
                $flags[] = 'no-search';
            }
            $flagsStr = $flags === [] ? '' : ' [' . implode(',', $flags) . ']';

            $context->step(
                sprintf('%-25s %s (children: %d)%s', $row['name'], $row['url'], count($row['available']), $flagsStr),
                true,
                $row['name'],
            );
            $r->file($row['name']);
        }

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'rows' => $rows,
            'count' => count($rows),
        ];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }

    /**
     * @param array<string, mixed> $rules
     *
     * @return array{name: string, url: string, php: string, available: list<string>, auth: bool, search: bool, default: bool}
     */
    private function row(string $name, string $url, string $php, array $rules): array
    {
        $available = $rules['available'] ?? [];
        if (!is_array($available)) {
            $available = [];
        }

        $targets = [];
        foreach ($available as $target => $pattern) {
            if (is_string($target)) {
                $targets[] = $target;
            } elseif (is_string($pattern)) {
                $targets[] = $pattern;
            }
        }

        return [
            'name' => $name,
            'url' => $url,
            'php' => $php,
            'available' => $targets,
            'auth' => isset($rules['auth']) && $rules['auth'] === true,
            'search' => !isset($rules['search']) || $rules['search'] === true,
            'default' => isset($rules['default']) && $rules['default'] === true,
        ];
    }
}
