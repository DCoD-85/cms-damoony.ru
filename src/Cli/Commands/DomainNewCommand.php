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

        $domain = (string) ($args['domain'] ?? '');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (str_contains($domain, '..')) {
            $r->error('Недопустимый домен (содержит "..")');
            return $r;
        }

        $namespace = (string) ($args['namespace'] ?? str_replace(['.', '-'], '_', $domain));
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

        // 2. Папки и файлы алиаса www
        Fs::mkdir($domainDir);
        $r->file(Fs::rel($domainDir, $this->paths->root()));

        $allias = 'www';
        $alliasDir = $this->paths->allias($domain, $allias);
        Fs::mkdir($alliasDir);

        $algoritmDir = $this->paths->algoritm($domain, $allias);
        Fs::mkdir($algoritmDir);

        // 2a. Страница root: PageCompiler (единый шаблон) + rules + editor (правило 3 файлов)
        $compiler = new PageCompiler();
        $rootPhp = $compiler->compile($namespace . '\\' . $allias, 'algoritm', []);

        Fs::writeAtomic($algoritmDir . '/algoritm.php', $rootPhp);
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        Fs::writeAtomic(
            $algoritmDir . '/algoritm.rules.json',
            json_encode(
                ['available' => [], 'auth' => false, 'search' => true, 'default' => false],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ) . "\n",
        );
        $r->file(Fs::rel($algoritmDir . '/algoritm.rules.json', $this->paths->root()));

        Fs::writeAtomic($algoritmDir . '/algoritm.editor.json', "[]\n");
        $r->file(Fs::rel($algoritmDir . '/algoritm.editor.json', $this->paths->root()));

        // 3. Глобальная схема www
        $schemesDir = $domainDir . '/.alliasscheme';
        Fs::mkdir($schemesDir);
        $cssUrl = '/media/' . $domain . '/www/skin/default/css/main.css';
        $jsUrl = '/media/' . $domain . '/www/skin/default/js/main.js';
        $schemePhp = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'main', 'slot' => 'center'],\n    ],\n    'meta' => [\n        'css' => ['{$cssUrl}'],\n        'js' => ['{$jsUrl}', '/media/shared/scripts/cliajax.js'],\n    ],\n];\n";
        Fs::writeAtomic($schemesDir . '/www.php', $schemePhp);
        Fs::writeAtomic($schemesDir . '/www.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($schemesDir . '/www.php', $this->paths->root()));

        // 4. Глобальный блок center
        $globalDir = $domainDir . '/.alliasglobal/center';
        Fs::mkdir($globalDir);

        $centerPhp = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\UI\\Region\\RegionContext;\nuse Damoony\\UI\\Region\\RegionInterface;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class alliasglobal_center implements RegionInterface\n{\n    public function name(): string { return 'center'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('center');\n        \$wrap->append(Builder::p()->content('Добро пожаловать на сайт!'));\n        return \$wrap;\n    }\n}\n";
        Fs::writeAtomic($globalDir . '/center.php', $centerPhp);
        Fs::writeAtomic($globalDir . '/center.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($globalDir . '/center.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($globalDir . '/center.php', $this->paths->root()));

        // 5. Конфиг (все ключи)
        $site['domains_real'][$domain] = $domain;
        $site['domains_available'][$domain] = true;
        $site['domains_namespace'][$domain] = $namespace;
        $site['alliases_real'][$domain] ??= ['www' => 'www'];
        $site['alliases_available'][$domain][$allias] = true;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        // 4a. Скин по умолчанию (после записи config — skin:new проверяет domains_real)
        $skinCmd = new SkinNewCommand($this->paths);
        $skinResult = $skinCmd->run(
            ['domain' => $domain, 'allias' => $allias, 'name' => 'default'],
            $context,
        );
        foreach ($skinResult->steps as $s) {
            $r->steps[] = $s;
        }
        foreach ($skinResult->created as $f) {
            $r->file($f);
        }
        foreach ($skinResult->warnings as $w) {
            $r->warn($w);
        }
        foreach ($skinResult->errors as $e) {
            $r->error($e);
        }

        $context->step('Домен создан', true, $domain);
        $context->step('Конфиг обновлён');

        return $r;
    }
}
