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

        $logic = (string) ($args['logic'] ?? 'alpha.0.0');
        $alliasDir = $this->paths->allias($logic, $domain, $allias);
        if (!Fs::isDir($alliasDir)) {
            $r->error('Алиас не найден');
            return $r;
        }

        $blockDir = $this->paths->domain($logic, $domain) . '/alliasglobal/' . $block;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $fillerPhp = $blockDir . '/' . $block . '_' . $allias . '.php';
        if (Fs::isFile($fillerPhp)) {
            $r->error('Filler уже существует');
            return $r;
        }

        $php = "<?php\n\ndeclare(strict_types=1);\n\nuse alliasglobal_filler_trait;\n\nclass {$block}_{$allias}\n{\n    use alliasglobal_filler_trait;\n}\n";
        Fs::writeAtomic($fillerPhp, $php);
        $r->file(Fs::rel($fillerPhp, $this->paths->root()));

        $data = "<?php\n\nreturn [\n    'wrapper' => ['tag' => 'div', 'attrs' => ['class' => '{$block}-{$allias}']],\n    'sections' => [],\n];\n";
        $dataFile = $blockDir . '/' . $block . '_' . $allias . '.data.php';
        Fs::writeAtomic($dataFile, $data);
        $r->file(Fs::rel($dataFile, $this->paths->root()));

        $site['fillers'][$domain][$allias][$block] = $site['fillers'][$domain][$allias][$block] ?? true;
        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Filler создан', true, $block . '_' . $allias);

        return $r;
    }
}