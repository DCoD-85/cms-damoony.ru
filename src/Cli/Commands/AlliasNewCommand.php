<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageNamespace;

final class AlliasNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'allias:new';
    }

    public function description(): string
    {
        return 'Создать алиас домена';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $profile = (string) ($args['profile'] ?? 'minimal');

        if (!in_array($profile, ['clean', 'minimal', 'full'], true)) {
            $r->error('Некорректный профиль (clean, minimal, full)');
            return $r;
        }

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
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

        $alliasDir = $this->paths->allias($realDomain, $name);
        if (Fs::isDir($alliasDir)) {
            $r->error('Алиас уже существует');
            return $r;
        }

        // 2. Папки и файлы
        Fs::mkdir($alliasDir);
        $r->file(Fs::rel($alliasDir, $this->paths->root()));

        $algoritmDir = $this->paths->algoritm($realDomain, $name);
        Fs::mkdir($algoritmDir);

        // 2a. Root-страница: content() = null (рендеринг через scheme → blocksglobal_*)
        $namespace = PageNamespace::fromSite($site, $realDomain);
        $rootPhp = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$name};\n\n"
            . "use Damoony\\UI\\Algoritm\\Page;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class algoritm extends Page\n"
            . "{\n"
            . "    protected function content(): Node|null\n"
            . "    {\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($algoritmDir . '/algoritm.php', $rootPhp);
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

        // 2b. Схема по умолчанию для алиаса (clean: пропустить)
        if ($profile !== 'clean') {
            $schemesDir = $this->paths->domain($realDomain) . '/.schemesglobal';
            if (!Fs::isDir($schemesDir)) {
                Fs::mkdir($schemesDir);
            }

            $schemeName = $name;
            $schemeEditor = $schemesDir . '/' . $schemeName . '.editor.json';
            $schemePhp = $schemesDir . '/' . $schemeName . '.php';
            $schemeRules = $schemesDir . '/' . $schemeName . '.rules.json';

            if (!is_file($schemePhp)) {
                $defaultScheme = [
                    'tag' => 'div',
                    'class' => 'layout',
                    'children' => [
                        ['tag' => 'header', 'slot' => 'top'],
                        ['tag' => 'aside', 'slot' => 'left'],
                        ['tag' => 'main', 'slot' => 'center'],
                        ['tag' => 'aside', 'slot' => 'right'],
                        ['tag' => 'footer', 'slot' => 'footer'],
                        ['tag' => 'section', 'slot' => 'bottom'],
                        ['tag' => 'div', 'slot' => 'policy'],
                        ['tag' => 'div', 'slot' => 'toast'],
                    ],
                    'meta' => [
                        'css' => ['/media/' . $realDomain . '/' . $name . '/skin/{skin}/css/main.css'],
                        'js' => [
                            '/media/' . $realDomain . '/' . $name . '/skin/{skin}/js/main.js',
                            '/media/shared/scripts/cliajax.js',
                        ],
                    ],
                ];
                Fs::writeAtomic($schemePhp, "<?php\n\nreturn " . var_export($defaultScheme, true) . ";\n");
                $r->file(Fs::rel($schemePhp, $this->paths->root()));

                Fs::writeAtomic(
                    $schemeEditor,
                    json_encode(['scheme' => ['children' => [['type' => 'slot', 'name' => 'center']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
                );
                $r->file(Fs::rel($schemeEditor, $this->paths->root()));

                Fs::writeAtomic($schemeRules, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
                $r->file(Fs::rel($schemeRules, $this->paths->root()));
            }
        } // end if profile !== clean

        // 2c. FULL профиль для алиаса (без глобальных блоков и shared media — они создаются domain:new)
        if ($profile === 'full') {
            $mediaAlias = $this->paths->mediaAlias($realDomain, $name);
            Fs::mkdir($mediaAlias . '/css');
            Fs::mkdir($mediaAlias . '/scripts');
            Fs::mkdir($mediaAlias . '/images');
            Fs::writeAtomic($mediaAlias . '/css/.gitkeep', '');
            Fs::writeAtomic($mediaAlias . '/scripts/.gitkeep', '');
            Fs::writeAtomic($mediaAlias . '/images/.gitkeep', '');

            $i18nLocal = $this->paths->i18nLocal($realDomain, $name);
            Fs::mkdir($i18nLocal);
            Fs::writeAtomic($i18nLocal . '/ru.json', "{}\n");
            Fs::writeAtomic($i18nLocal . '/en.json', "{}\n");

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
            $dbLocal = $this->paths->dbLocal($realDomain, $name);
            Fs::mkdir($dbLocal);
            Fs::writeAtomic($dbLocal . '/schema.json', $schemaJson);

            $localBlocks = ['top', 'left', 'center', 'right', 'bottom', 'footer', 'policy'];
            $localDir = $alliasDir . '/.blockslocal';
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
                $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Damoony\\UI\\Region\\RegionContext;\nuse Damoony\\UI\\Region\\RegionInterface;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class blockslocal_{$name}_{$b} implements RegionInterface\n{\n    public function name(): string { return '{$b}'; }\n    public function enabled(): bool { return true; }\n\n    public function render(RegionContext \$context): Node\n    {\n        \$wrap = Builder::div('site-local-{$b}');\n        \$wrap->append(Builder::p()->content('это локальный блок {$b}'));{$extra}        return \$wrap;\n    }\n}\n";
                Fs::writeAtomic($lDir . '/' . $b . '.php', $php);
                Fs::writeAtomic($lDir . '/' . $b . '.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
                Fs::writeAtomic($lDir . '/' . $b . '.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
                $r->file(Fs::rel($lDir . '/' . $b . '.php', $this->paths->root()));
            }

            $slDir = $alliasDir . '/.schemeslocal';
            Fs::mkdir($slDir);
            $tsScheme = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'main', 'slot' => 'center'],\n    ],\n    'meta' => [\n        'css' => [],\n        'js' => [],\n    ],\n];\n";
            Fs::writeAtomic($slDir . '/testscheme.php', $tsScheme);
            Fs::writeAtomic($slDir . '/testscheme.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");

            $tsDir = $algoritmDir . '/testscheme';
            Fs::mkdir($tsDir);
            $tsPhp = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace}\\{$name};\n\nuse Damoony\\UI\\Algoritm\\Page;\nuse Damoony\\UI\\Builder;\nuse Damoony\\UI\\Node;\n\nfinal class algoritm_testscheme extends Page\n{\n    protected function content(): Node|null\n    {\n        return Builder::div('page-content')->content('Test Scheme Page');\n    }\n}\n";
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.php', $tsPhp);
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.rules.json', json_encode(['available' => ['' => '^$'], 'auth' => false], JSON_PRETTY_PRINT) . "\n");
            Fs::writeAtomic($tsDir . '/algoritm_testscheme.editor.json', "[]\n");
            $r->file(Fs::rel($tsDir . '/algoritm_testscheme.php', $this->paths->root()));
        }

        // 3. Конфиг (все ключи)
        $site['alliases_real'][$realDomain][$name] = $name;
        $site['alliases_available'][$realDomain][$name] = true;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        // 4. Скин по умолчанию (clean: пропустить)
        if ($profile !== 'clean') {
            $skinCmd = new SkinNewCommand($this->paths);
            $skinResult = $skinCmd->run(
                ['domain' => $domain, 'allias' => $name, 'name' => 'default'],
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
            if (!$skinResult->ok) {
                $r->ok = false;
            }
        } // end if profile !== clean

        $context->step('Алиас создан (профиль: ' . $profile . ')', true, $domain . '/' . $name);

        return $r;
    }
}
