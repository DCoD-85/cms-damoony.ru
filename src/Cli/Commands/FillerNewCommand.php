<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class FillerNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'filler:new';
    }

    public function description(): string
    {
        return 'Создать filler для блока алиаса';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $block = (string) ($args['block'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            $r->error('Некорректный блок');
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
        if (!isset($site['alliases_real'][$domain][$allias])) {
            $r->error('Алиас не найден в конфиге');
            return $r;
        }

        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain]
            ?? str_replace(['.', '-'], '_', $realDomain));

        // Путь по realDomain (папка app/)
        $alliasDir = $this->paths->allias($realDomain, $allias);
        if (!Fs::isDir($alliasDir)) {
            $r->error('Алиас не найден на диске: ' . $realDomain . '/' . $allias);
            return $r;
        }

        // Базовый блок должен существовать в .blocksglobal (иначе filler не имеет смысла)
        $globalBlockDir = $this->paths->domain($realDomain) . '/.blocksglobal/' . $block;
        if (!Fs::isDir($globalBlockDir)) {
            $r->error('Блок не найден: ' . $block);
            return $r;
        }

        // Filler пишется в {real}/{alias}/.blockslocal/{block}/{block}.php
        $blockDir = $this->paths->allias($realDomain, $allias) . '/.blockslocal/' . $block;
        $className = 'blockslocal_' . $allias . '_' . $block;
        $fillerPhp = $blockDir . '/' . $block . '.php';
        $rulesFile = $blockDir . '/' . $block . '.rules.json';
        $editorFile = $blockDir . '/' . $block . '.editor.json';

        if (Fs::isFile($fillerPhp) || Fs::isFile($rulesFile)) {
            $r->error('Filler уже существует');
            return $r;
        }
        if (!Fs::isDir($blockDir)) {
            Fs::mkdir($blockDir);
        }

        $php = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "use Damoony\\UI\\Region\\RegionContext;\n"
            . "use Damoony\\UI\\Region\\RegionInterface;\n"
            . "use Damoony\\UI\\Builder;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class {$className} implements RegionInterface\n"
            . "{\n"
            . "    public function name(): string\n"
            . "    {\n"
            . "        return '{$block}';\n"
            . "    }\n\n"
            . "    public function enabled(): bool\n"
            . "    {\n"
            . "        return true;\n"
            . "    }\n\n"
            . "    public function render(RegionContext \$context): Node\n"
            . "    {\n"
            . "        \$wrap = Builder::div('{$block}-{$allias}');\n"
            . "        \$wrap->append(Builder::p()->content('это локальный блок {$block}'));\n"
            . "        return \$wrap;\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($fillerPhp, $php);
        $r->file(Fs::rel($fillerPhp, $this->paths->root()));

        Fs::writeAtomic($rulesFile, json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($editorFile, json_encode([], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($rulesFile, $this->paths->root()));
        $r->file(Fs::rel($editorFile, $this->paths->root()));

        // Конфиг: fillers[domain][allias][block] = true (по входному домену, как остальные ключи)
        $site['fillers'][$realDomain][$allias][$block] ??= true;
        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Filler создан', true, $className);

        return $r;
    }
}
