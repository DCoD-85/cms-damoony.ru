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
use Damoony\UI\Region\BlockCompiler;
use RuntimeException;

/**
 * filler:write для схемы domains_app: JSON-блоки -> PHP локального блока.
 */
final class FillerWriteCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'filler:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в филлер';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $block = (string) ($args['block'] ?? '');
        if (preg_match(self::NAME_RE, $block) !== 1) {
            $r->error('Некорректный блок');

            return $r;
        }

        $blocks = $this->parseBlocks($args, $r);
        if ($blocks === null) {
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

        $sessionId = trim((string) ($args['session'] ?? ''));

        try {
            $this->sessionGuard->assertAllowed($sessionId, $target->folder(), 'filler:write');
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $baseDir = $this->sessionGuard->baseDir($target->folder(), $sessionId);
        $blockDir = $baseDir . '/' . $target->aliasName() . '/.blockslocal/' . $block;
        if (!Fs::isDir($blockDir)) {
            $r->error('Filler не найден');

            return $r;
        }

        $className = 'blockslocal_' . $target->aliasName() . '_' . $block;

        /** @var list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $compiledBlocks */
        $compiledBlocks = $blocks;

        $php = new BlockCompiler()->compile($target->project->namespace, $className, $block, $compiledBlocks);

        if ($context->isDryRun()) {
            $context->step('Dry-run: контент филлера ' . $block . ' будет перезаписан');
            $r->data = ['block' => $block, 'blocks' => count($blocks), 'dry_run' => true];

            return $r;
        }

        $targetFile = $blockDir . '/' . $block . '.php';
        Fs::writeAtomic($targetFile, $php);
        $r->file(Fs::rel($targetFile, $this->paths->root()));

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'block' => $block,
            'blocks' => count($blocks),
        ];

        $context->step('Контент филлера обновлён', true, $className);

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return list<array<string, mixed>>|null
     */
    private function parseBlocks(array $args, Result $r): ?array
    {
        $json = (string) ($args['json'] ?? '');
        $jsonB64 = (string) ($args['json_b64'] ?? '');

        if ($jsonB64 !== '') {
            $decoded = base64_decode($jsonB64, true);
            if ($decoded === false) {
                $r->error('Некорректный base64');

                return null;
            }
            $json = $decoded;
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');

            return null;
        }

        /** @var list<array<string, mixed>> $list */
        $list = [];

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $list[] = $block;
            }
        }

        return $list;
    }
}
