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

        if ($context->isDryRun()) {
            $context->step('Dry-run: домен ' . $domain . ' (namespace: ' . $namespace . ') будет создан');
            $r->data = ['domain' => $domain, 'namespace' => $namespace, 'dry_run' => true];
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
        Fs::writeAtomic($langDir . '/algoritm_lang.rules.json', json_encode(['available' => ['' => '^$', 'tests' => '^tests$'], 'auth' => false], JSON_PRETTY_PRINT) . "\n");
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
            $blockCssList = [];
            foreach (['top', 'left', 'center', 'right', 'bottom', 'footer', 'policy', 'toast'] as $b) {
                $blockCssList[] = '/media/' . $domain . '/' . $allias . '/skin/{skin}/css/' . $b . '.css';
            }
            $blockCssExport = var_export($blockCssList, true);
            $mainCssUrl = '/media/' . $domain . '/' . $allias . '/css/main.css';
            $schemePhp = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout',\n    'children' => [\n        ['tag' => 'header', 'slot' => 'top'],\n        ['tag' => 'aside', 'slot' => 'left'],\n        ['tag' => 'main', 'slot' => 'center'],\n        ['tag' => 'aside', 'slot' => 'right'],\n        ['tag' => 'footer', 'slot' => 'footer'],\n        ['tag' => 'section', 'slot' => 'bottom'],\n        ['tag' => 'div', 'slot' => 'policy'],\n        ['tag' => 'div', 'slot' => 'toast'],\n    ],\n    'meta' => [\n        'css' => array_merge(['{$mainCssUrl}', '{$cssUrl}'], {$blockCssExport}),\n        'js' => ['{$jsUrl}', '/media/shared/scripts/cliajax.js'],\n    ],\n];\n";
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
        $site['domains_real'][$domain] = $namespace;
        $site['domains_mirrors'][$domain] = $domain;
        $site['domains_mirrors_available'][$domain] = true;
        $site['alliases'][$domain][$allias] = ['available' => true, 'langs' => ['ru', 'en']];

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        // 4a. Скин по умолчанию для www (если не clean)
        if ($profile !== 'clean') {
            $skinCmd = new SkinNewCommand($this->paths);
            $skinResult = $skinCmd->run(
                ['domain' => $domain, 'allias' => 'www', 'name' => 'default'],
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
        }

        // 4b. FULL: используем AssemblyBuilder для полной структуры
        if ($profile === 'full') {
            $builder = new \Damoony\Core\Assembly\AssemblyBuilder($this->paths);
            $builder->build('developer', $domain, [
                'namespace' => $namespace,
                'real' => $domain,
                'alias' => $allias,
            ]);
        }

        $context->step('Домен создан (профиль: ' . $profile . ')', true, $domain);
        $context->step('Конфиг обновлён');

        return $r;
    }
}
