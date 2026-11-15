<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class DomainUnbindCommand implements CommandInterface
{
    private const ALL_KEYS = [
        'domains_real', 'domains_mirrors', 'domains_mirrors_available', 'domains_titles',
        'domains_skins', 'domains_skins_allowed', 'domains_stub', 'domains_scheme',
        'alliases_real', 'alliases_available', 'alliases_langs', 'alliases_titles',
        'alliases_stubs', 'alliases_skins', 'alliases_skins_allowed', 'fillers',
        'domains_langs'
    ];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'domain:unbind';
    }

    public function description(): string
    {
        return 'Отвязать внешний домен (хост) от реального';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');

        if ($domain === '') {
            $r->error('Укажите --domain=[host]');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }

        $config = Config::fromFile($siteFile);
        $data = $config->all();

        if (isset($data['domains_real'][$domain])) {
            $r->error("Нельзя отвязать реальный домен '{$domain}'. Удалите его через domain:remove");
            return $r;
        }
        $real = $data['domains_mirrors'][$domain] ?? '';

        if ($real === '') {
            $context->step("Домен '{$domain}' и так не привязан", true);
            return $r;
        }

        foreach (self::ALL_KEYS as $k) {
            if (isset($data[$k][$domain])) {
                unset($data[$k][$domain]);
            }
        }

        file_put_contents(
            $siteFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $context->step("Домен '{$domain}' успешно отвязан", true);
        return $r;
    }
}
