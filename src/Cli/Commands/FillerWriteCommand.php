<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Region\BlockCompiler;

final class FillerWriteCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'filler:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в filler';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $block = (string) ($args['block'] ?? '');

        $jsonB64 = (string) ($args['json_b64'] ?? '');
        $json = (string) ($args['json'] ?? '');

        if ($jsonB64 !== '') {
            $json = base64_decode($jsonB64, true) ?: '';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            $r->error('Некорректный алиас или имя блока');
            return $r;
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        $blockDir = $this->paths->domain($realDomain) . '/.alliasglobal/' . $block;
        $target = $blockDir . '/' . $block . '_' . $allias . '.php';

        if (!Fs::isFile($target)) {
            $r->error('Filler не найден (сначала создайте его через filler:new)');
            return $r;
        }

        $className = 'alliasglobal_' . $block . '_' . $allias;
        $compiler = new BlockCompiler();
        $phpCode = $compiler->compile($namespace, $className, $block, $blocks);

        Fs::writeAtomic($target, $phpCode);

        $r->file(Fs::rel($target, $this->paths->root()));
        $context->step('Контент филлера обновлен', true, $className);

        return $r;
    }
}
