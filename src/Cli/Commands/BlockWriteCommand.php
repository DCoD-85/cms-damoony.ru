<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Region\BlockCompiler;

final class BlockWriteCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в блок';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');

        $jsonB64 = (string) ($args['json_b64'] ?? '');
        $json = (string) ($args['json'] ?? '');

        if ($jsonB64 !== '') {
            $json = base64_decode($jsonB64, true) ?: '';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя блока');
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

        $blockDir = $this->paths->domain($realDomain) . '/.blocksglobal/' . $name;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $className = 'blocksglobal_' . $name;
        $compiler = new BlockCompiler();
        $phpCode = /** @var list<array<string, mixed>> \$blocks */
        $compiler->compile($namespace, $className, $name, array_values($blocks));

        $target = $blockDir . '/' . $name . '.php';
        Fs::writeAtomic($target, $phpCode);

        $r->file(Fs::rel($target, $this->paths->root()));
        $context->step('Контент блока обновлен', true, $name);

        return $r;
    }
}
