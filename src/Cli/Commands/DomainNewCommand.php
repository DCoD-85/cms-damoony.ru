<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

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
        $profile = (string) ($args['profile'] ?? 'minimal');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (str_contains($domain, '..')) {
            $r->error('Недопустимый домен (содержит "..")');
            return $r;
        }
        if (!in_array($profile, ['clean', 'minimal', 'full'], true)) {
            $r->error('Некорректный профиль (clean, minimal, full)');
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

        // 2a. Root-страница: редирект на язык по умолчанию
        $rootPhp = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$allias};\n\n"
            . "use Damoony\\Core\\Http\\Response;\n"
            . "use Damoony\\UI\\Algoritm\\Page;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class algoritm extends Page\n"
            . "{\n"
            . "    protected function content(): Node|Response|null\n"
            . "    {\n"
            . "        \$lang = \$this->args['lang'] ?? '';\n"
            . "        if (\$lang === '') {\n"
            . "            \$default = 'ru';\n"
            . "            \$site = \$this->context->service('site');\n"
            . "            if (\$site instanceof \\Damoony\\Core\\Config) {\n"
            . "                \$default = (string) \$site->getByKey(['domains_langs', \$this->context->domain->real, 0], 'ru');\n"
            . "            }\n"
            . "            return Response::redirect('/' . \$default . '/');\n"
            . "        }\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($algoritmDir . '/algoritm.php', $rootPhp);

        // 2a2. Lang страница
        $langDir = $algoritmDir . '/lang';
        Fs::mkdir($langDir);
        $langPhp = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$allias};\n\n"
            . "use Damoony\\UI\\Algoritm\\Page;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class algoritm_lang extends Page\n"
            . "{\n"
            . "    protected function content(): Node|null\n"
            . "    {\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($langDir . '/algoritm_lang.php', $langPhp);
        Fs::writeAtomic($langDir . '/algoritm_lang.rules.json', json_encode(['available' => ['' => '^$'], 'auth' => false], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($langDir . '/algoritm_lang.editor.json', "[]\n");
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        $rootRules = ['available' => ['' => '^$', 'lang' => '^([a-z]{2})$'], 'auth' => false, 'search' => true, 'default' => false];
        if ($profile === 'full') {
            $rootRules['available']['testscheme'] = '^testscheme$';
        }
        Fs::writeAtomic(
            $algoritmDir . '/algoritm.rules.json',
            json_encode($rootRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $r->file(Fs::rel($algoritmDir . '/algoritm.rules.json', $this->paths->root()));

        Fs::writeAtomic($algoritmDir . '/algoritm.editor.json', "[]\n");
        $r->file(Fs::rel($algoritmDir . '/algoritm.editor.json', $this->paths->root()));

        // 3. Глобальная схема www (minimal: создать; clean: пропустить)
        if ($profile === 'minimal' || $profile === 'full') {
            $schemesDir = $domainDir . '/.schemesglobal';
            Fs::mkdir($schemesDir);
            $cssUrl = '/media/' . $domain . '/www/skin/{skin}/css/main.css';
            $jsUrl = '/media/' . $domain . '/www/skin/{skin}/js/main.js';
            $schemePhp = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'header', 'slot' => 'top'],\n        ['tag' => 'aside', 'slot' => 'left'],\n        ['tag' => 'main', 'slot' => 'center'],\n        ['tag' => 'aside', 'slot' => 'right'],\n        ['tag' => 'footer', 'slot' => 'footer'],\n        ['tag' => 'section', 'slot' => 'bottom'],\n        ['tag' => 'div', 'slot' => 'policy'],\n        ['tag' => 'div', 'slot' => 'toast'],\n    ],\n    'meta' => [\n        'css' => ['{$cssUrl}'],\n        'js' => ['{$jsUrl}', '/media/shared/scripts/cliajax.js'],\n    ],\n];\n";
            Fs::writeAtomic($schemesDir . '/www.php', $schemePhp);
            Fs::writeAtomic($schemesDir . '/www.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($schemesDir . '/www.php', $this->paths->root()));
        }

        // 4. Глобальный блок center (minimal: создать; clean: пропустить)
        if ($profile === 'minimal' || $profile === 'full') {
            $globalDir = $domainDir . '/.blocksglobal/center';
            Fs::mkdir($globalDir);

            $centerPhp = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\UI\\Region\\RegionContext;\nuse Damoony\\UI\\Region\\RegionInterface;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class blocksglobal_center implements RegionInterface\n{\n    public function name(): string { return 'center'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('center');\n        \$wrap->append(Builder::p()->content('это глобальный блок center'));\n        return \$wrap;\n    }\n}\n";
            Fs::writeAtomic($globalDir . '/center.php', $centerPhp);
            Fs::writeAtomic($globalDir . '/center.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
            Fs::writeAtomic($globalDir . '/center.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($globalDir . '/center.php', $this->paths->root()));
        }

        // 5. Конфиг (все ключи)
        $site['domains_real'][$domain] = $domain;
        $site['domains_available'][$domain] = true;
        $site['domains_namespace'][$domain] = $namespace;
        $site['domains_structure'][$domain] = 'v4';
        $site['alliases_real'][$domain] ??= ['www' => 'www'];
        $site['alliases_available'][$domain][$allias] = true;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        // 4b. FULL: остальные блоки + локальные + БД + i18n + media + testscheme
        if ($profile === 'full') {
            // media/shared
            $mediaShared = $this->paths->mediaShared();
            Fs::mkdir($mediaShared . '/css');
            Fs::mkdir($mediaShared . '/scripts');
            Fs::mkdir($mediaShared . '/images');
            Fs::writeAtomic($mediaShared . '/css/.gitkeep', '');
            Fs::writeAtomic($mediaShared . '/images/.gitkeep', '');
            $cliajaxSrc = $this->paths->root() . '/stubs/cliajax.js';
            if (is_file($cliajaxSrc)) {
                Fs::writeAtomic($mediaShared . '/scripts/cliajax.js', (string) file_get_contents($cliajaxSrc));
                $r->file(Fs::rel($mediaShared . '/scripts/cliajax.js', $this->paths->root()));
            }

            // media/{domain}/{alias}
            $mediaAlias = $this->paths->mediaAlias($domain, $allias);
            Fs::mkdir($mediaAlias . '/css');
            Fs::mkdir($mediaAlias . '/scripts');
            Fs::mkdir($mediaAlias . '/images');
            Fs::writeAtomic($mediaAlias . '/css/.gitkeep', '');
            Fs::writeAtomic($mediaAlias . '/scripts/.gitkeep', '');
            Fs::writeAtomic($mediaAlias . '/images/.gitkeep', '');

            // i18n
            $i18nGlobal = $this->paths->i18nGlobal($domain);
            Fs::mkdir($i18nGlobal);
            Fs::writeAtomic($i18nGlobal . '/ru.json', "{}\n");
            Fs::writeAtomic($i18nGlobal . '/en.json', "{}\n");

            $i18nLocal = $this->paths->i18nLocal($domain, $allias);
            Fs::mkdir($i18nLocal);
            Fs::writeAtomic($i18nLocal . '/ru.json', "{}\n");
            Fs::writeAtomic($i18nLocal . '/en.json', "{}\n");

            // DB schemas
            $schema = [
                'tables' => [
                    'test_items' => [
                        'columns' => [
                            'id' => ['type' => 'int', 'primary' => true, 'auto_increment' => true],
                            'title' => ['type' => 'varchar', 'length' => 255],
                            'body' => ['type' => 'text'],
                            'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
                        ],
                    ],
                ],
            ];
            $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            $dbGlobal = $this->paths->dbGlobal($domain);
            Fs::mkdir($dbGlobal);
            Fs::writeAtomic($dbGlobal . '/schema.json', $schemaJson);

            $dbLocal = $this->paths->dbLocal($domain, $allias);
            Fs::mkdir($dbLocal);
            Fs::writeAtomic($dbLocal . '/schema.json', $schemaJson);

            // Global blocks (без center)
            $globalBlocks = ['top', 'left', 'right', 'bottom', 'footer', 'policy', 'toast'];
            foreach ($globalBlocks as $b) {
                $gDir = $domainDir . '/.blocksglobal/' . $b;
                Fs::mkdir($gDir);
                $extra = '';
                if ($b === 'top') {
                    $extra = "\n        \$site = \$context->service('site');\n"
                        . "        \$langs = ['ru', 'en'];\n"
                        . "        if (\$site instanceof \\Damoony\\Core\\Config) {\n"
                        . "            \$configured = \$site->getByKey(['alliases_langs', \$context->domain->real, \$context->domain->allias], []);\n"
                        . "            if (is_array(\$configured) && \$configured !== []) { \$langs = \$configured; }\n"
                        . "            else { \$configured = \$site->getByKey(['domains_langs', \$context->domain->real], ['ru', 'en']); if (is_array(\$configured) && \$configured !== []) { \$langs = \$configured; } }\n"
                        . "        }\n"
                        . "        \$switcher = Builder::div('lang-switcher');\n"
                        . "        foreach (\$langs as \$l) {\n"
                        . "            \$switcher->append(Builder::a('/' . \$l . '/')->content(strtoupper(\$l)));\n"
                        . "        }\n"
                        . "        \$wrap->append(\$switcher);\n";
                }
                $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\UI\\Region\\RegionContext;\nuse Damoony\\UI\\Region\\RegionInterface;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class blocksglobal_{$b} implements RegionInterface\n{\n    public function name(): string { return '{$b}'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('site-{$b}');\n        \$wrap->append(Builder::p()->content('это глобальный блок {$b}'));{$extra}        return \$wrap;\n    }\n}\n";
                Fs::writeAtomic($gDir . '/' . $b . '.php', $php);
                Fs::writeAtomic($gDir . '/' . $b . '.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
                Fs::writeAtomic($gDir . '/' . $b . '.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
                $r->file(Fs::rel($gDir . '/' . $b . '.php', $this->paths->root()));
            }

            // Local blocks
            $localBlocks = ['top', 'left', 'center', 'right', 'bottom', 'footer', 'policy'];
            $localDir = $domainDir . '/' . $allias . '/.blockslocal';
            foreach ($localBlocks as $b) {
                $lDir = $localDir . '/' . $b;
                Fs::mkdir($lDir);
                $extra = '';
                if ($b === 'top') {
                    $extra = "\n        \$site = \$context->service('site');\n"
                        . "        \$langs = ['ru', 'en'];\n"
                        . "        if (\$site instanceof \\Damoony\\Core\\Config) {\n"
                        . "            \$configured = \$site->getByKey(['alliases_langs', \$context->domain->real, \$context->domain->allias], []);\n"
                        . "            if (is_array(\$configured) && \$configured !== []) { \$langs = \$configured; }\n"
                        . "            else { \$configured = \$site->getByKey(['domains_langs', \$context->domain->real], ['ru', 'en']); if (is_array(\$configured) && \$configured !== []) { \$langs = \$configured; } }\n"
                        . "        }\n"
                        . "        \$switcher = Builder::div('lang-switcher');\n"
                        . "        foreach (\$langs as \$l) {\n"
                        . "            \$switcher->append(Builder::a('/' . \$l . '/')->content(strtoupper(\$l)));\n"
                        . "        }\n"
                        . "        \$wrap->append(\$switcher);\n";
                }
                $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\UI\\Region\\RegionContext;\nuse Damoony\\UI\\Region\\RegionInterface;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class blockslocal_{$allias}_{$b} implements RegionInterface\n{\n    public function name(): string { return '{$b}'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('site-local-{$b}');\n        \$wrap->append(Builder::p()->content('это локальный блок {$b}'));{$extra}        return \$wrap;\n    }\n}\n";
                Fs::writeAtomic($lDir . '/' . $b . '.php', $php);
                Fs::writeAtomic($lDir . '/' . $b . '.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
                Fs::writeAtomic($lDir . '/' . $b . '.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
                $r->file(Fs::rel($lDir . '/' . $b . '.php', $this->paths->root()));
            }

            // Local scheme
            $slDir = $domainDir . '/' . $allias . '/.schemeslocal';
            Fs::mkdir($slDir);
            $tsScheme = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'main', 'slot' => 'center'],\n    ],\n    'meta' => [\n        'css' => [],\n        'js' => [],\n    ],\n];\n";
            Fs::writeAtomic($slDir . '/testscheme.php', $tsScheme);
            Fs::writeAtomic($slDir . '/testscheme.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");

            // Test page
            $tsDir = $algoritmDir . '/testscheme';
            Fs::mkdir($tsDir);
            $tsPhp = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace}\\{$allias};\n\nuse Damoony\\UI\\Algoritm\\Page;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class algoritm_testscheme extends Page\n{\n    protected function content(): Node|null\n    {\n        return Builder::div('page-content')->content('Test Scheme Page');\n    }\n}\n";
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.php', $tsPhp);
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.rules.json', json_encode(['available' => ['' => '^$'], 'auth' => false], JSON_PRETTY_PRINT) . "\n");
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.editor.json', "[]\n");
            $r->file(Fs::rel($tsDir . '/algoritm_testscheme.php', $this->paths->root()));
        }

        // 4a. Скин по умолчанию (только для minimal)
        if ($profile === 'minimal' || $profile === 'full') {
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
        }

        $context->step('Домен создан (профиль: ' . $profile . ')', true, $domain);
        $context->step('Конфиг обновлён');

        return $r;
    }
}
