<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class DomainNewCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    public function name(): string
    {
        return 'domain:new';
    }

    public function description(): string
    {
        return 'Создать домен';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        if ($domain === '' || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }

        $namespace = $args['namespace'] ?? str_replace(['.', '-'], '_', $domain);
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $namespace)) {
            $r->error('Некорректный namespace');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];

        if (isset($site['domains_real'][$domain])) {
            $r->error('Домен уже существует в конфиге');
            return $r;
        }

        $domainDir = $this->paths->domain($domain);
        if (Fs::isDir($domainDir)) {
            $r->error('Папка домена уже существует');
            return $r;
        }

        Fs::mkdir($domainDir);
        $r->file(Fs::rel($domainDir, $this->paths->root()));
        $context->step('Домен создан', true, $domain);

        $allias = 'www';
        $alliasDir = $this->paths->allias($domain, $allias);
        Fs::mkdir($alliasDir);
        $algoritmDir = $this->paths->algoritm($domain, $allias);
        Fs::mkdir($algoritmDir);

        // 1. Создаём .rules.json
        Fs::writeAtomic($algoritmDir . '/.rules.json', json_encode(['available' => ['']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($algoritmDir . '/.rules.json', $this->paths->root()));

        // 2. Создаём algoritm.php
        $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace}\\{$allias};\n\nuse Damoony\\Algoritm\\Algoritm;\nuse Damoony\\Ui\\Builder;\nuse Damoony\\Ui\\Node;\nuse Damoony\\Http\\Response;\n\nfinal class algoritm extends Page\n{\n    public function default(): Node|Response|null\n    {\n        return Builder::div('page')->content('Главная страница');\n    }\n}\n";
        Fs::writeAtomic($algoritmDir . '/algoritm.php', $php);
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        // 3. Создаём глобальную схему www.php
        $schemesDir = $domainDir . '/.alliasscheme';
        Fs::mkdir($schemesDir);
        $schemePhp = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'main', 'slot' => 'center'],\n    ],\n];\n";
        Fs::writeAtomic($schemesDir . '/www.php', $schemePhp);
        Fs::writeAtomic($schemesDir . '/www.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($schemesDir . '/www.php', $this->paths->root()));

        // 4. Создаём глобальный блок center по умолчанию
        $globalDir = $domainDir . '/.alliasglobal/center';
        Fs::mkdir($globalDir);

        $centerPhp = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\Region\\RegionContext;\nuse Damoony\\Region\\RegionInterface;\nuse Damoony\\Ui\\Builder;\nuse Damoony\\Ui\\Node;\n\nfinal class alliasglobal_center implements RegionInterface\n{\n    public function name(): string { return 'center'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('center');\n        \$wrap->append(Builder::p()->content('Добро пожаловать на сайт!'));\n        return \$wrap;\n    }\n}\n";
        Fs::writeAtomic($globalDir . '/center.php', $centerPhp);
        Fs::writeAtomic($globalDir . '/center.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($globalDir . '/center.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($globalDir . '/center.php', $this->paths->root()));

        $site['domains_real'][$domain] = $domain;
        $site['domains_available'][$domain] = true;
        $site['domains_namespace'][$domain] = $namespace;
        $site['alliases_real'][$domain] ??= ['www' => 'www'];

        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Конфиг обновлён');

        return $r;
    }
}
