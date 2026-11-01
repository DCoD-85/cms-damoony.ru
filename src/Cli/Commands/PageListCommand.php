<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Page\PagePath;
use Damoony\Page\PageRules;

final class PageListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
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

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }
        $realDomain = (string) $site['domains_real'][$domain];

        $algDir = $this->paths->algoritm($realDomain, $allias);
        if (!is_dir($algDir)) {
            $r->error('Папка algoritm не найдена');
            return $r;
        }

        // Корневая страница (algoritm/algoritm.php)
        $rootPhp = $algDir . '/algoritm.php';
        $rootRules = $algDir . '/algoritm.rules.json';
        if (is_file($rootPhp)) {
            $rules = PageRules::load($rootRules);
            $r->data['rows'][] = $this->row('__root__', '/', $rootPhp, $rules);
        }

        // Рекурсивный обход подпапок
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($algDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isDir()) {
                continue;
            }
            $dir = $file->getPathname();
            if ($dir === $algDir) {
                continue;
            }

            $rel = trim(substr($dir, strlen($algDir)), '/\\');
            $rel = str_replace('\\', '/', $rel);
            if ($rel === '' || str_starts_with($rel, '.')) {
                continue;
            }

            $classChain = 'algoritm_' . str_replace('/', '_', $rel);
            $php = $dir . '/' . $classChain . '.php';
            $rules = $dir . '/' . $classChain . '.rules.json';

            if (!is_file($php)) {
                continue;
            }

            $rulesData = PageRules::load($rules);
            $r->data['rows'][] = $this->row($rel, '/' . $rel, $php, $rulesData);
        }

        $rows = $r->data['rows'] ?? [];
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

        $r->data['count'] = count($rows);
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }

    /**
     * @param array{available: list<string>, auth: bool, search: bool, default: bool} $rules
     *
     * @return array{name: string, url: string, php: string, available: list<string>, auth: bool, search: bool, default: bool}
     */
    private function row(string $name, string $url, string $php, array $rules): array
    {
        $available = $rules['available'] ?? [];
        if (!is_array($available)) {
            $available = [];
        }
        $available = array_values(array_filter($available, 'is_string'));

        return [
            'name' => $name,
            'url' => $url,
            'php' => $php,
            'available' => $available,
            'auth' => (bool) ($rules['auth'] ?? false),
            'search' => (bool) ($rules['search'] ?? true),
            'default' => (bool) ($rules['default'] ?? false),
        ];
    }
}