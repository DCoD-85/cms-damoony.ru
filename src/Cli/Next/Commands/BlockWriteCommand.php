<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Region\BlockCompiler;
use RuntimeException;

/**
 * block:write для схемы domains_app: JSON-блоки -> безопасный PHP блока.
 *
 * Namespace берётся из проекта; каталог учитывает сессию (SessionGuard::baseDir).
 */
final class BlockWriteCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'block:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в блок';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя блока');

            return $r;
        }

        $blocks = $this->parseBlocks($args, $r);
        if ($blocks === null) {
            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $sessionId = trim((string) ($args['session'] ?? ''));

        try {
            $this->sessionGuard->assertAllowed($sessionId, $project->folder, 'block:write');
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blockDir = $this->sessionGuard->baseDir($project->folder, $sessionId) . '/.blocksglobal/' . $name;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');

            return $r;
        }

        $className = 'blocksglobal_' . $name;

        /** @var list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $compiledBlocks */
        $compiledBlocks = $blocks;

        $php = new BlockCompiler()->compile($project->namespace, $className, $name, $compiledBlocks);

        if ($context->isDryRun()) {
            $context->step('Dry-run: контент блока ' . $name . ' будет перезаписан');
            $r->data = [
                'project' => $project->key,
                'block' => $name,
                'blocks' => count($blocks),
                'dry_run' => true,
            ];

            return $r;
        }

        $target = $blockDir . '/' . $name . '.php';
        Fs::writeAtomic($target, $php);
        $r->file(Fs::rel($target, $this->paths->root()));

        $r->data = ['project' => $project->key, 'block' => $name, 'blocks' => count($blocks)];

        $context->step('Контент блока обновлён', true, $name);

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return list<array<string, mixed>>|null
     */
    private function parseBlocks(array $args, Result $r): ?array
    {
        $jsonB64 = (string) ($args['json_b64'] ?? '');
        $json = (string) ($args['json'] ?? '');

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
