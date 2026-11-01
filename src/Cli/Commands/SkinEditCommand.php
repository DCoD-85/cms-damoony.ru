<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class SkinEditCommand implements CommandInterface
{
    private const ALLOWED_FILES = ['css/main.css', 'js/main.js'];
    private const MAX_SIZE = 1048576; // 1 MB
    private const SAFE_CSS_RE = '#@import|url\(\s*["\']?\s*javascript:#i';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'skin:edit';
    }

    public function description(): string
    {
        return 'Редактировать файл скина (только css/main.css, js/main.js)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $file = (string) ($args['file'] ?? '');
        $content = (string) ($args['content'] ?? '');
        $contentB64 = (string) ($args['content_b64'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя скина');
            return $r;
        }
        if (!in_array($file, self::ALLOWED_FILES, true)) {
            $r->error('Недопустимый файл (разрешены: ' . implode(', ', self::ALLOWED_FILES) . ')');
            return $r;
        }

        if ($contentB64 !== '') {
            $decoded = base64_decode($contentB64, true);
            if ($decoded === false) {
                $r->error('Некорректный base64');
                return $r;
            }
            $content = $decoded;
        }
        if ($content === '') {
            $r->error('Укажите --content или --content_b64');
            return $r;
        }
        if (strlen($content) > self::MAX_SIZE) {
            $r->error('Файл больше ' . self::MAX_SIZE . ' байт');
            return $r;
        }

        if (str_ends_with($file, '.css') && preg_match(self::SAFE_CSS_RE, $content) === 1) {
            $r->error('CSS содержит небезопасные конструкции (@import / javascript: url)');
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

        $skinDir = $this->paths->media($realDomain, $allias) . '/skin/' . $name;
        if (!Fs::isDir($skinDir)) {
            $r->error('Скин не найден');
            return $r;
        }

        $target = $skinDir . '/' . $file;
        Fs::writeAtomic($target, $content);
        $r->file(Fs::rel($target, $this->paths->root()));

        $context->step('Файл скина обновлён', true, $file . ' (' . strlen($content) . ' B)');

        return $r;
    }
}
