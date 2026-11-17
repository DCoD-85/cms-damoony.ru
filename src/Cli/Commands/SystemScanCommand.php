<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;

/**
 * system:scan — эталон структуры проекта: что лишнее, чего не хватает.
 */
final class SystemScanCommand implements CommandInterface
{
    private const ROOT_KEEP = [
        'ai_agent', 'app', 'bin', 'config', 'content', 'data', 'lang',
        'migrations', 'src', 'stubs', 'vendor', 'var', 'www',
        '_dev', '_doc', '_dist',
        '.git', '.github', '.gitignore', '.gitattributes',
        'composer.json', 'composer.lock', 'composer.phar', 'README.md',
    ];

    private const WWW_KEEP = [
        'admin', 'api', 'downloads', 'media', 'stubs',
        'content.php', 'forms.php', 'index.php', 'router.php', 'request.php',
    ];

    private const REQUIRED = ['bin', 'src', 'vendor', 'www', 'config'];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'system:scan';
    }

    public function description(): string
    {
        return 'Сканер структуры проекта (что лишнее, чего не хватает)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $root = $this->paths->root();

        $garbageRoot = $this->unexpected($root, self::ROOT_KEEP);
        $garbageWww = $this->unexpected($root . '/www', self::WWW_KEEP);
        $missing = [];

        foreach (self::REQUIRED as $required) {
            if (!is_dir($root . '/' . $required)) {
                $missing[] = $required . '/';
            }
        }

        foreach ($garbageRoot as $item) {
            $context->step('🗑️  УДАЛИТЬ: ' . $item, false);
        }
        foreach ($garbageWww as $item) {
            $context->step('🗑️  УДАЛИТЬ: www/' . $item, false);
        }
        foreach ($missing as $item) {
            $context->step('❌ ОТСУТСТВУЕТ: ' . $item, false);
        }

        $ideal = $garbageRoot === [] && $garbageWww === [] && $missing === [];

        $context->step(
            $ideal ? '✅ ПРОЕКТ В ИДЕАЛЬНОМ СОСТОЯНИИ' : '⚠️ Найдены отклонения от эталона',
            $ideal,
        );

        $r->data = [
            'ideal' => $ideal,
            'garbage_root' => $garbageRoot,
            'garbage_www' => $garbageWww,
            'missing' => $missing,
        ];

        return $r;
    }

    /**
     * @param list<string> $keep
     *
     * @return list<string>
     */
    private function unexpected(string $dir, array $keep): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!in_array($item, $keep, true)) {
                $out[] = $item;
            }
        }
        sort($out);

        return $out;
    }
}
