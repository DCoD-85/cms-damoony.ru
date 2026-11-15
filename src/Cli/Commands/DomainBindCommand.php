<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class DomainBindCommand implements CommandInterface
{
    private const ALL_KEYS = [
        'domains_real',
        'domains_mirrors',
        'domains_mirrors_available',
        'domains_stub',
        'domains_scheme',
        'alliases',
        'fillers',
    ];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'domain:bind';
    }

    public function description(): string
    {
        return 'Привязать внешний домен (хост) к реальному домену';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $real = (string) ($args['real'] ?? '');

        if ($domain === '' || $real === '') {
            $r->error('Укажите --domain=[host] и --real=[real_domain]');
            return $r;
        }
        if ($domain === $real) {
            $r->error('Домен и реальный домен не могут совпадать (используйте domain:new)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }

        $config = Config::fromFile($siteFile);
        $data = $config->all();

        if (!isset($data['domains_real'][$real])) {
            $r->error("Реальный домен '{$real}' не существует или не является self-mapped");
            return $r;
        }

        // 1. Очищаем домен от любых старых следов, чтобы он не считался реальным
        foreach (self::ALL_KEYS as $k) {
            if (isset($data[$k][$domain]) && $k !== 'domains_real' && $k !== 'domains_mirrors') {
                unset($data[$k][$domain]);
            }
        }

        // 2. Привязываем
        $data['domains_mirrors'][$domain] = $real;
        $data['domains_mirrors_available'][$domain] = true;

        file_put_contents(
            $siteFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $context->step("Домен '{$domain}' успешно привязан к '{$real}'", true);
        return $r;
    }
}
