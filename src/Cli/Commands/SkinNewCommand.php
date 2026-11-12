<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class SkinNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'skin:new';
    }

    public function description(): string
    {
        return 'Создать скин алиаса (css/main.css, js/main.js, img/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? 'default');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match(self::NAME_RE, $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match(self::NAME_RE, $name)) {
            $r->error('Некорректное имя скина (a-z0-9_)');
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

        $alliasDir = $this->paths->allias($realDomain, $allias);
        if (!Fs::isDir($alliasDir)) {
            $r->error('Алиас не найден');
            return $r;
        }

        $skinDir = $this->paths->media($realDomain, $allias) . '/skin/' . $name;
        if (Fs::isDir($skinDir)) {
            $r->error('Скин уже существует');
            return $r;
        }

        Fs::mkdir($skinDir . '/css');
        Fs::mkdir($skinDir . '/js');
        Fs::mkdir($skinDir . '/img');

        $css = "/* Skin: {$name} */\n"
            . "body { font-family: system-ui, sans-serif; margin: 0; padding: 0; }\n"
            . ".page-content { max-width: 960px; margin: 0 auto; padding: 16px; }\n";
        Fs::writeAtomic($skinDir . '/css/main.css', $css);
        $r->file(Fs::rel($skinDir . '/css/main.css', $this->paths->root()));

        $js = "// Skin: {$name}\n";
        Fs::writeAtomic($skinDir . '/js/main.js', $js);
        $r->file(Fs::rel($skinDir . '/js/main.js', $this->paths->root()));

        $meta = [
            'name' => $name,
            'version' => '1.0',
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        Fs::writeAtomic(
            $skinDir . '/skin.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $r->file(Fs::rel($skinDir . '/skin.json', $this->paths->root()));

        // Регистрация в site.config — под realDomain (FS-ключ), а не alias
        $site['alliases_skins'][$realDomain][$allias] ??= $name;
        $allowed = $site['alliases_skins_allowed'][$realDomain][$allias] ?? [];
        if (!is_array($allowed)) {
            $allowed = [];
        }
        if (!in_array($name, $allowed, true)) {
            $allowed[] = $name;
        }
        $site['alliases_skins_allowed'][$realDomain][$allias] = $allowed;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $context->step('Скин создан', true, $name);

        return $r;
    }
}
