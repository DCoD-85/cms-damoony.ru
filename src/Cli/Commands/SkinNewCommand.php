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
        if (!preg_match(self::NAME_RE, $name)) {
            $r->error('Некорректное имя скина (a-z0-9_)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        
        $realDomain = (string) ($site['domains_mirrors'][$domain] ?? $domain);
        if (!isset($site['domains_real'][$realDomain])) {
            $r->error('Домен не найден');
            return $r;
        }

        $isBase = isset($args['base']);
        
        if ($isBase) {
            $skinDir = $this->paths->mediaRoot() . '/' . $realDomain . '/.skin/' . $name;
        } else {
            if (!preg_match(self::NAME_RE, $allias)) {
                $r->error('Некорректный алиас');
                return $r;
            }
            $alliasDir = $this->paths->allias($realDomain, $allias);
            if (!Fs::isDir($alliasDir)) {
                $r->error('Алиас не найден');
                return $r;
            }
            $skinDir = $this->paths->media($realDomain, $allias) . '/skin/' . $name;
        }

        if (Fs::isDir($skinDir)) {
            $r->error('Скин уже существует');
            return $r;
        }

        $baseSkinDir = $this->paths->mediaRoot() . '/' . $realDomain . '/.skin';

        if (Fs::isDir($baseSkinDir) && $baseSkinDir !== $skinDir) {
            $this->copyDir($baseSkinDir, $skinDir);
            $r->file(Fs::rel($skinDir, $this->paths->root()));
        } else {
            Fs::mkdir($skinDir . '/css');
            Fs::mkdir($skinDir . '/js');
            Fs::mkdir($skinDir . '/img');
            Fs::writeAtomic($skinDir . '/css/main.css', "/* Skin: {$name} */\n");
            Fs::writeAtomic($skinDir . '/js/main.js', "// Skin: {$name}\n");
            $meta = ['name' => $name, 'version' => '1.0', 'created_at' => gmdate('Y-m-d\TH:i:s\Z')];
            Fs::writeAtomic($skinDir . '/skin.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            $r->file(Fs::rel($skinDir, $this->paths->root()));
        }

        $context->step('Скин создан', true, $name);
        return $r;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) mkdir($dst, 0775, true);
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}