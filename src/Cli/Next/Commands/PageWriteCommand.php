<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\ContentRefs;
use Damoony\Cli\Next\Support\PageAddress;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PagePath;
use RuntimeException;

/**
 * page:write для схемы domains_app: JSON-блоки → безопасный PHP.
 */
final class PageWriteCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly ContentRefs $contentRefs,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'page:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в существующую страницу';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $address = PageAddress::fromArgs($args, $r);
        if ($address === null) {
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
            $this->sessionGuard->assertAllowed($sessionId, $target->folder());
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blocks = $this->parseBlocks($args, $r);
        if ($blocks === null) {
            return $r;
        }

        $page = PagePath::resolve(
            $this->paths,
            $target->folder(),
            $target->aliasName(),
            $address->name,
            $address->parent,
            $sessionId !== '' ? $sessionId : null,
        );

        if (!Fs::isFile($page->phpFile)) {
            $r->error('Страница не найдена. Сначала используйте page:new');

            return $r;
        }

        $namespace = $target->project->namespace . '\\' . $target->aliasName();
        $withSchema = isset($args['with_schema']) && $args['with_schema'] === '1';

        /** @var list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $compiledBlocks */
        $compiledBlocks = $blocks;

        $php = new PageCompiler()->compile($namespace, $page->classChain, $compiledBlocks, $withSchema);

        if ($context->isDryRun()) {
            $context->step('Dry-run: контент страницы ' . $address->display() . ' будет перезаписан');
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'page' => $address->display(),
                'blocks' => count($blocks),
                'file' => Fs::rel($page->phpFile, $this->paths->root()),
                'dry_run' => true,
            ];

            return $r;
        }

        $oldPhp = (string) @file_get_contents($page->phpFile);

        Fs::writeAtomic($page->phpFile, $php);
        Fs::writeAtomic(
            $page->editorFile,
            json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n",
        );

        $relPath = Fs::rel($page->phpFile, $this->paths->root());
        $r->file($relPath);

        $refsChanged = $this->contentRefs->sync(
            $target->folder(),
            $target->aliasName(),
            $address->display(),
            $oldPhp,
            $php,
        );

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'page' => $address->display(),
            'blocks' => count($blocks),
            'file' => $relPath,
            'editor_file' => Fs::rel($page->editorFile, $this->paths->root()),
            'content_refs_updated' => $refsChanged,
        ];

        $context->step('Контент страницы обновлён', true, $address->display());

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return list<array<string, mixed>>|null
     */
    private function parseBlocks(array $args, Result $r): ?array
    {
        if (isset($args['json'])) {
            $json = (string) $args['json'];
        } elseif (isset($args['json_file'])) {
            $file = (string) $args['json_file'];
            if (!is_file($file)) {
                $r->error('Файл json_file не найден');

                return null;
            }
            $json = (string) file_get_contents($file);
        } elseif (isset($args['json_b64'])) {
            $decoded = base64_decode((string) $args['json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');

                return null;
            }
            $json = $decoded;
        } else {
            $json = '[]';
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');

            return null;
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values(array_filter($blocks, 'is_array'));

        return $list;
    }
}
