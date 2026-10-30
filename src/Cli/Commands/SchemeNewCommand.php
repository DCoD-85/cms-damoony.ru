<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class SchemeNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:new';
    }

    public function description(): string
    {
        return 'Создать схему домена';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя схемы');
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

        $schemesDir = $this->paths->domain($domain) . '/.alliasscheme';
        $editorFile = $schemesDir . '/' . $name . '.editor.json';
        $phpFile = $schemesDir . '/' . $name . '.php';
        $rulesFile = $schemesDir . '/' . $name . '.rules.json';

        if (Fs::isFile($editorFile) || Fs::isFile($phpFile)) {
            $r->error('Схема уже существует');
            return $r;
        }

        Fs::mkdir($schemesDir);

        // Сканируем доступные блоки
        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        $fields = [];

        if (is_dir($blocksDir)) {
            $dirs = array_filter(scandir($blocksDir) ?: [], fn ($d) => $d !== '.' && $d !== '..' && is_dir($blocksDir . '/' . $d));
            foreach ($dirs as $bName) {
                $bRulesFile = $blocksDir . '/' . $bName . '/' . $bName . '.rules.json';
                $bRules = is_file($bRulesFile) ? json_decode((string)file_get_contents($bRulesFile), true) : [];
                $engineType = $bRules['type'] ?? 'mixed';
                $fields[] = ['name' => $bName, 'type' => $engineType];
            }
        }
        usort($fields, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // 1. Создаем editor.json
        $editorData = [
            'fields' => $fields,
            'scheme' => [
                'children' => [],
                'scripts' => [],
            ],
        ];
        Fs::writeAtomic($editorFile, json_encode($editorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        // 2. Создаем пустой php
        $data = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [],\n    'scripts' => [],\n];\n";
        Fs::writeAtomic($phpFile, $data);

        // 3. Создаем rules.json
        Fs::writeAtomic($rulesFile, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");

        $r->file(Fs::rel($editorFile, $this->paths->root()));
        $r->file(Fs::rel($phpFile, $this->paths->root()));
        $r->file(Fs::rel($rulesFile, $this->paths->root()));
        $context->step('Схема создана', true, $name);

        return $r;
    }
}
