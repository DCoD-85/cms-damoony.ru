<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * skin:edit для схемы domains_app: css/*.css и js/main.js с guard'ами.
 */
final class SkinEditCommand implements CommandInterface
{
    private const MAX_SIZE = 1048576;
    private const SAFE_CSS_RE = '#@import|url\(\s*["\']?\s*javascript:#i';
    private const FILE_RE = '#^(css/[a-z0-9_]+\.css|js/main\.js)$#';
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'skin:edit';
    }

    public function description(): string
    {
        return 'Редактировать файл скина (css/*.css, js/main.js)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $file = (string) ($args['file'] ?? '');

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя скина');

            return $r;
        }
        if (preg_match(self::FILE_RE, $file) !== 1) {
            $r->error('Недопустимый файл (разрешены: css/*.css, js/main.js)');

            return $r;
        }

        $content = $this->content($args, $r);
        if ($content === null) {
            return $r;
        }

        if (strlen($content) > self::MAX_SIZE) {
            $r->error('Файл больше ' . self::MAX_SIZE . ' байт');

            return $r;
        }

        if (str_ends_with($file, '.css')) {
            if (preg_match(self::SAFE_CSS_RE, $content) === 1) {
                $r->error('CSS содержит небезопасные конструкции (@import / javascript: url)');

                return $r;
            }

            $open = substr_count($content, '{');
            $close = substr_count($content, '}');

            if ($close > $open) {
                $r->error('Некорректный CSS: лишние закрывающие скобки }');

                return $r;
            }
            if ($open > $close) {
                $missing = $open - $close;
                $content = rtrim($content) . "\n" . str_repeat("}\n", $missing);
                $context->step('CSS auto-repaired', true, "Added {$missing} missing closing brace(s)");
            }
        }

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $sessionId = trim((string) ($args['session'] ?? ''));

        try {
            $this->sessionGuard->assertAllowed($sessionId, $target->folder(), 'skin:edit');
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $skinDir = $this->paths->media($target->folder(), $target->aliasName()) . '/skin/' . $name;
        if (!Fs::isDir($skinDir)) {
            $r->error('Скин не найден');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: %s скина %s будет записан (%d B)', $file, $name, strlen($content)));
            $r->data = ['skin' => $name, 'file' => $file, 'dry_run' => true];

            return $r;
        }

        $targetFile = $skinDir . '/' . $file;
        Fs::mkdir(dirname($targetFile));
        Fs::writeAtomic($targetFile, $content);
        $r->file(Fs::rel($targetFile, $this->paths->root()));

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'skin' => $name,
            'file' => $file,
            'bytes' => strlen($content),
        ];

        $context->step('Файл скина обновлён', true, $file . ' (' . strlen($content) . ' B)');

        return $r;
    }

    /**
     * @param array<string, string> $args
     */
    private function content(array $args, Result $r): ?string
    {
        $content = (string) ($args['content'] ?? '');
        $contentB64 = (string) ($args['content_b64'] ?? '');

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
}
