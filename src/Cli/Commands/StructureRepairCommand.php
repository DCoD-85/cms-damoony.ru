<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PageNamespace;

final class StructureRepairCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'structure:repair';
    }

    public function description(): string
    {
        return 'Восстановить структуру доменов, алиасов и схем';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Укажите --domain=example.ru');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден в конфиге');
            return $r;
        }

        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = PageNamespace::fromSite($site, $domain);
        $domainDir = $this->paths->domain($realDomain);
        $fixed = 0;

        // 1. Восстановление алиасов (3 файла root-страницы с единым префиксом)
        $alliases = $site['alliases_real'][$domain] ?? ['www' => 'www'];
        $realAliases = array_unique(array_values($alliases));

        foreach ($realAliases as $alias) {
            $alias = (string) $alias;
            if ($alias === '') {
                continue;
            }

            $algoritmDir = $domainDir . '/' . $alias . '/algoritm';
            if (!is_dir($algoritmDir)) {
                Fs::mkdir($algoritmDir);
            }

            // 1a. Root PHP — генерируем через PageCompiler (единый шаблон)
            $phpFile = $algoritmDir . '/algoritm.php';
            if (!is_file($phpFile)) {
                $compiler = new PageCompiler();
                $php = $compiler->compile($namespace . '\\' . $alias, 'algoritm', []);
                Fs::writeAtomic($phpFile, $php);
                $context->step("Восстановлен algoritm.php для алиаса: {$alias}");
                $fixed++;
            }

            // 1b. Root rules
            $rulesFile = $algoritmDir . '/algoritm.rules.json';
            if (!is_file($rulesFile)) {
                Fs::writeAtomic(
                    $rulesFile,
                    json_encode(
                        ['available' => [], 'auth' => false, 'search' => true, 'default' => false],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ) . "\n",
                );
                $context->step("Восстановлен algoritm.rules.json для алиаса: {$alias}");
                $fixed++;
            }

            // 1c. Root editor
            $editorFile = $algoritmDir . '/algoritm.editor.json';
            if (!is_file($editorFile)) {
                Fs::writeAtomic($editorFile, "[]\n");
                $context->step("Восстановлен algoritm.editor.json для алиаса: {$alias}");
                $fixed++;
            }
        }

        // 2. Восстановление rules.json для схем
        $schemesDir = $domainDir . '/.alliasscheme';
        if (is_dir($schemesDir)) {
            $files = glob($schemesDir . '/*.php') ?: [];
            foreach ($files as $file) {
                $name = basename($file, '.php');
                $rulesFile = $schemesDir . '/' . $name . '.rules.json';
                if (!is_file($rulesFile)) {
                    Fs::writeAtomic($rulesFile, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
                    $context->step("Восстановлен {$name}.rules.json для схемы");
                    $fixed++;
                }
            }
        }

        // 3. Восстановление rules.json и editor.json для блоков
        $blocksDir = $domainDir . '/.alliasglobal';
        if (is_dir($blocksDir)) {
            $dirs = array_filter(
                scandir($blocksDir) ?: [],
                static fn (string $d): bool => $d !== '.' && $d !== '..' && is_dir($blocksDir . '/' . $d),
            );
            foreach ($dirs as $bName) {
                $rulesFile = $blocksDir . '/' . $bName . '/' . $bName . '.rules.json';
                if (!is_file($rulesFile)) {
                    Fs::writeAtomic(
                        $rulesFile,
                        json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n",
                    );
                    $context->step("Восстановлен {$bName}.rules.json");
                    $fixed++;
                }

                $editorFile = $blocksDir . '/' . $bName . '/' . $bName . '.editor.json';
                if (!is_file($editorFile)) {
                    Fs::writeAtomic($editorFile, "[]\n");
                    $context->step("Восстановлен {$bName}.editor.json");
                    $fixed++;
                }
            }
        }

        if ($fixed === 0) {
            $context->step('Структура в порядке, исправлений не требуется.');
        } else {
            $context->step("Ремонт завершён. Исправлено элементов: {$fixed}", true);
        }

        return $r;
    }
}
