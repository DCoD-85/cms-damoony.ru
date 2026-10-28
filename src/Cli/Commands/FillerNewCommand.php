<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

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

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $block = $args['block'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
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

        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain]
            ?? str_replace(['.', '-'], '_', $realDomain));

        $alliasDir = $this->paths->allias($domain, $allias);
        if (!Fs::isDir($alliasDir)) {
            $r->error('Алиас не найден');
            return $r;
        }

        $blockDir = $this->paths->domain($domain) . '/.alliasglobal/' . $block;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $className = 'alliasglobal_' . $block . '_' . $allias;
        $fillerPhp = $blockDir . '/' . $block . '_' . $allias . '.php';
        $dataFile = $blockDir . '/' . $block . '_' . $allias . '.data.php';

        // ИСПРАВЛЕНИЕ: Проверяем наличие ОБОИХ файлов
        if (Fs::isFile($fillerPhp) && Fs::isFile($dataFile)) {
            $r->error('Filler уже существует');
            return $r;
        }

        $php = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "use Damoony\\Region\\RegionContext;\n"
            . "use Damoony\\Region\\RegionInterface;\n"
            . "use Damoony\\Ui\\Builder;\n"
            . "use Damoony\\Ui\\Node;\n\n"
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
            . "        \$wrap->append(Builder::p()->content('Filler {$block} для алиаса {$allias}'));\n"
            . "        return \$wrap;\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($fillerPhp, $php);
        $r->file(Fs::rel($fillerPhp, $this->paths->root()));

        $data = "<?php\n\nreturn [\n    'wrapper' => ['tag' => 'div', 'attrs' => ['class' => '{$block}-{$allias}']],\n    'sections' => [],\n];\n";
        Fs::writeAtomic($dataFile, $data);
        $r->file(Fs::rel($dataFile, $this->paths->root()));

        $site['fillers'][$domain][$allias][$block] ??= true;
        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Filler создан', true, $className);

        return $r;
    }
}
