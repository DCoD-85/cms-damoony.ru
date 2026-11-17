<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use JsonException;
use RuntimeException;

/**
 * block:edit для схемы domains_app: точечная запись одного из 3 файлов блока.
 *
 * `code` требует engine.config.security.allow_php_blocks=true и --allow_php.
 */
final class BlockEditCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';
    private const FILES = ['code', 'rules', 'editor'];
    private const TYPES = ['script', 'visual', 'mixed'];
    private const MAX_RULES_SIZE = 65536;
    private const MAX_EDITOR_SIZE = 1048576;
    private const MAX_CODE_SIZE = 1048576;

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'block:edit';
    }

    public function description(): string
    {
        return 'Правка файлов блока (code|rules|editor) через CodeMirror';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $file = (string) ($args['file'] ?? '');

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя блока');

            return $r;
        }
        if (!in_array($file, self::FILES, true)) {
            $r->error('Недопустимый file (разрешены: code, rules, editor)');

            return $r;
        }

        $content = $this->content($args, $r);
        if ($content === null) {
            return $r;
        }

        $maxSize = match ($file) {
            'rules' => self::MAX_RULES_SIZE,
            'editor' => self::MAX_EDITOR_SIZE,
            default => self::MAX_CODE_SIZE,
        };
        if (strlen($content) > $maxSize) {
            $r->error('Файл больше ' . $maxSize . ' байт');

            return $r;
        }

        if ($file === 'rules' && !$this->validateRules($content, $r)) {
            return $r;
        }
        if ($file === 'editor' && json_decode($content, true) === null && trim($content) !== 'null') {
            $r->error('Некорректный JSON: editor.json');

            return $r;
        }
        if ($file === 'code') {
            if (($this->engineConfig()['security']['allow_php_blocks'] ?? false) !== true) {
                $r->error('Raw PHP в блоках запрещён (security.allow_php_blocks=false в engine.config)');

                return $r;
            }
            if (!isset($args['allow_php'])) {
                $r->error('Используйте --allow_php для подтверждения записи сырого PHP');

                return $r;
            }
            if (!str_starts_with($content, '<?php')) {
                $r->error('PHP-код должен начинаться с <?php');

                return $r;
            }
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blockDir = $this->paths->blocksGlobal($project->folder) . '/' . $name;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');

            return $r;
        }

        $target = $blockDir . '/' . $name . match ($file) {
            'code' => '.php',
            'rules' => '.rules.json',
            default => '.editor.json',
        };

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: %s для блока %s будет записан (%d B)', $file, $name, strlen($content)));
            $r->data = ['project' => $project->key, 'block' => $name, 'file' => $file, 'dry_run' => true];

            return $r;
        }

        Fs::writeAtomic($target, $content);
        $r->file(Fs::rel($target, $this->paths->root()));

        $r->data = ['project' => $project->key, 'block' => $name, 'file' => $file, 'bytes' => strlen($content)];

        $context->step('Файл блока обновлён', true, $file . ' (' . strlen($content) . ' B)');

        return $r;
    }

    /**
     * @param array<string, string> $args
     */
    private function content(array $args, Result $r): ?string
    {
        $contentB64 = (string) ($args['content_b64'] ?? '');
        $content = (string) ($args['content'] ?? '');

        if ($contentB64 !== '') {
            $decoded = base64_decode($contentB64, true);
            if ($decoded === false) {
                $r->error('Некорректный base64');

                return null;
            }
            $content = $decoded;
        }

        if ($content === '') {
            $r->error('Укажите --content или --content_b64');

            return null;
        }

        return $content;
    }

    private function validateRules(string $content, Result $r): bool
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $r->error('Некорректный JSON: ' . $e->getMessage());

            return false;
        }

        if (!is_array($decoded)) {
            $r->error('rules.json должен быть объектом');

            return false;
        }
        if (isset($decoded['type']) && !in_array($decoded['type'], self::TYPES, true)) {
            $r->error('Недопустимый type (разрешены: ' . implode(', ', self::TYPES) . ')');

            return false;
        }
        if (isset($decoded['enabled']) && !is_bool($decoded['enabled'])) {
            $r->error('enabled должен быть bool');

            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function engineConfig(): array
    {
        $file = $this->paths->root() . '/config/engine.config';
        if (!is_file($file)) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = Config::fromFile($file)->all();

        return $config;
    }
}
