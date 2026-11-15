<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;

final class SystemScanCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths) {}

    public function name(): string { return 'system:scan'; }
    public function description(): string { return 'Сканер структуры: что оставить, что удалить'; }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $root = $this->paths->root();

        // ЭТАЛОН: что ДОЛЖНО быть в корне
        $rootKeep = [
            'ai_agent', 'app', 'bin', 'config', 'content', 'data', 'lang',
            'migrations', 'src', 'stubs', 'vendor', 'var', 'www',
            '_dev', '_doc', '_dist',
            '.git', '.github', '.gitignore', '.gitattributes',
            'composer.json', 'composer.lock', 'composer.phar', 'README.md',
        ];

        // ЭТАЛОН: что ДОЛЖНО быть в www
        $wwwKeep = [
            'admin', 'api', 'downloads', 'media', 'stubs',
            'content.php', 'forms.php', 'index.php', 'router.php', 'request.php',
        ];

        $totalGarbage = 0;

        // 1. Сканируем корень
        $context->step("=== КОРЕНЬ ПРОЕКТА ===", true);
        $items = scandir($root);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (!in_array($item, $rootKeep, true)) {
                $context->step("🗑️  УДАЛИТЬ: {$item}", false);
                $totalGarbage++;
            }
        }
        if ($totalGarbage === 0) {
            $context->step("✅ Корень чистый", true);
        }

        // 2. Сканируем www
        $context->step("=== ПАПКА www/ ===", true);
        $wwwPath = $root . '/www';
        $wwwGarbage = 0;
        if (is_dir($wwwPath)) {
            foreach (scandir($wwwPath) as $item) {
                if ($item === '.' || $item === '..') continue;
                if (!in_array($item, $wwwKeep, true)) {
                    $context->step("🗑️  УДАЛИТЬ: www/{$item}", false);
                    $wwwGarbage++;
                }
            }
        }
        if ($wwwGarbage === 0) {
            $context->step("✅ www чистый", true);
        }

        // 3. Проверяем наличие обязательных
        $context->step("=== ОБЯЗАТЕЛЬНЫЕ КОМПОНЕНТЫ ===", true);
        $missing = 0;
        foreach (['bin', 'src', 'vendor', 'www', 'config'] as $req) {
            if (!is_dir("{$root}/{$req}")) {
                $context->step("❌ ОТСУТСТВУЕТ: {$req}/", false);
                $missing++;
            }
        }
        if ($missing === 0) {
            $context->step("✅ Все обязательные папки на месте", true);
        }

        // Итог
        $context->step("=== ИТОГ ===", true);
        if ($totalGarbage === 0 && $wwwGarbage === 0 && $missing === 0) {
            $context->step("✅ ПРОЕКТ В ИДЕАЛЬНОМ СОСТОЯНИИ", true);
        } else {
            $context->step("⚠️ Мусор: {$totalGarbage} в корне, {$wwwGarbage} в www. Отсутствует: {$missing}.", false);
        }

        return $r;
    }
}
