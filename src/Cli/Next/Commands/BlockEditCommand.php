<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\BlockFileEditor;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * block:edit для схемы domains_app: точечная запись одного из 3 файлов блока.
 */
final class BlockEditCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly BlockFileEditor $editor,
    ) {
    }

    public function name(): string
    {
        return 'block:edit';
    }

    public function description(): string
    {
        return 'Правка файлов блока (code|rules|editor)';
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

        $content = $this->content($args, $r);
        if ($content === null) {
            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: %s блока %s будет записан (%d B)', $file, $name, strlen($content)));
            $r->data = ['project' => $project->key, 'block' => $name, 'file' => $file, 'dry_run' => true];

            return $r;
        }

        $blockDir = $this->paths->blocksGlobal($project->folder) . '/' . $name;

        try {
            $target = $this->editor->write($blockDir, $name, $file, $content, isset($args['allow_php']));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

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
}
