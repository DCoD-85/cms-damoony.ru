<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\BlockFileEditor;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * filler:edit для схемы domains_app: правка файлов локального блока алиаса.
 */
final class FillerEditCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly BlockFileEditor $editor,
    ) {
    }

    public function name(): string
    {
        return 'filler:edit';
    }

    public function description(): string
    {
        return 'Правка файлов филлера (code|rules|editor)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $block = (string) ($args['block'] ?? '');
        $file = (string) ($args['file'] ?? '');

        if (preg_match(self::NAME_RE, $block) !== 1) {
            $r->error('Некорректный блок');

            return $r;
        }

        $content = $this->content($args, $r);
        if ($content === null) {
            return $r;
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

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: %s филлера %s будет записан (%d B)', $file, $block, strlen($content)));
            $r->data = ['block' => $block, 'file' => $file, 'dry_run' => true];

            return $r;
        }

        $blockDir = $this->paths->blocksLocal($target->folder(), $target->aliasName()) . '/' . $block;

        try {
            $written = $this->editor->write($blockDir, $block, $file, $content, isset($args['allow_php']));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $r->file(Fs::rel($written, $this->paths->root()));
        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'block' => $block,
            'file' => $file,
            'bytes' => strlen($content),
        ];

        $context->step('Файл филлера обновлён', true, $file . ' (' . strlen($content) . ' B)');

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
