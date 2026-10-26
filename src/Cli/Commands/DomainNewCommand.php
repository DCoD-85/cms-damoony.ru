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
        private readonly string $defaultLogic = 'alpha.0.0',
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

        $logic = $args['logic'] ?? $this->defaultLogic;

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile)
            ? Config::fromFile($siteFile)->all()
            : [];

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

        Fs::writeAtomic($algoritmDir . '/.rules.json', json_encode(['available' => ['']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($algoritmDir . '/.rules.json', $this->paths->root()));

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