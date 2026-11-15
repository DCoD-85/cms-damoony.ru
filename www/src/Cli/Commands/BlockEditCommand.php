<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class BlockEditCommand implements CommandInterface
{
    private const ALLOWED_FILES = ['code', 'rules', 'editor'];
    private const MAX_RULES_SIZE = 65536;       // 64 KB
    private const MAX_EDITOR_SIZE = 1048576;    // 1 MB
    private const MAX_CODE_SIZE = 1048576;      // 1 MB
    private const BLOCK_TYPES = ['script', 'visual', 'mixed'];
    private const DOMAIN_RE = '/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/';
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:edit';
    }

    public function description(): string
    {
        return 'Редактировать блок (.php / .rules.json / .editor.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $file = (string) ($args['file'] ?? '');
        $content = (string) ($args['content'] ?? '');
        $contentB64 = (string) ($args['content_b64'] ?? '');
        $allowPhp = $this->boolFlag($args['allow_php'] ?? false);

        if (!preg_match(self::DOMAIN_RE, $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match(self::NAME_RE, $name)) {
            $r->error('Некорректное имя блока');
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

        $maxSize = match ($file) {
            'rules' => self::MAX_RULES_SIZE,
            'editor' => self::MAX_EDITOR_SIZE,
            'code' => self::MAX_CODE_SIZE,
        };
        if (strlen($content) > $maxSize) {
            $r->error('Файл больше ' . $maxSize . ' байт');
            return $r;
        }

        if ($file === 'rules') {
            $decoded = $this->decodeJson($content, $r);
            if ($decoded === null) {
                return $r;
            }
            if (!is_array($decoded)) {
                $r->error('rules.json должен быть объектом');
                return $r;
            }
            if (isset($decoded['type']) && !in_array($decoded['type'], self::BLOCK_TYPES, true)) {
                $r->error('Недопустимый type (разрешены: ' . implode(', ', self::BLOCK_TYPES) . ')');
                return $r;
            }
            if (isset($decoded['enabled']) && !is_bool($decoded['enabled'])) {
                $r->error('enabled должен быть bool');
                return $r;
            }
        } elseif ($file === 'editor') {
            if ($this->decodeJson($content, $r) === null) {
                return $r;
            }
        } elseif ($file === 'code') {
            $engine = $this->engineConfig();
            if (($engine['security']['allow_php_blocks'] ?? false) !== true) {
                $r->error('Raw PHP в блоках запрещён (security.allow_php_blocks=false в engine.config)');
                return $r;
            }
            if (!$allowPhp) {
                $r->error('Используйте --allow_php для подтверждения записи сырого PHP');
                return $r;
            }
            if (!str_starts_with($content, '<?php')) {
                $r->error('PHP-код должен начинаться с <?php');
                return $r;
            }
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

        $blockDir = $this->paths->domain($realDomain) . '/.blocksglobal/' . $name;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $target = match ($file) {
            'code' => $blockDir . '/' . $name . '.php',
            'rules' => $blockDir . '/' . $name . '.rules.json',
            'editor' => $blockDir . '/' . $name . '.editor.json',
        };

        Fs::writeAtomic($target, $content);
        $r->file(Fs::rel($target, $this->paths->root()));
        $context->step('Файл блока обновлён', true, $file . ' (' . strlen($content) . ' B)');

        return $r;
    }

    private function decodeJson(string $content, Result $r): mixed
    {
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $r->error('Некорректный JSON: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function engineConfig(): array
    {
        $file = $this->paths->root() . '/config/engine.config';
        /** @var array<string, mixed> $config */
        $config = is_file($file) ? Config::fromFile($file)->all() : [];
        return $config;
    }

    private function boolFlag(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return !in_array(strtolower($v), ['', '0', 'false', 'no', 'off'], true);
        }
        return (bool) $v;
    }
}
