<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PageAddress;
use Damoony\Cli\Support\SessionGuard;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PagePath;
use Damoony\UI\Page\PageRules;
use RuntimeException;

/**
 * page:new для схемы domains_app.
 *
 * Namespace берётся из domains_app.{key}.namespace — эвристики
 * str_replace(['.','-'],'_') больше нет.
 */
final class PageNewCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'page:new';
    }

    public function description(): string
    {
        return 'Создать страницу алиаса (php, rules.json, editor.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $address = PageAddress::fromArgs($args, $r);
        if ($address === null) {
            return $r;
        }
        if ($address->isRoot()) {
            $r->error('page:new не создаёт корневую страницу — она уже есть у алиаса');

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

        $page = PagePath::resolve(
            $this->paths,
            $target->folder(),
            $target->aliasName(),
            $address->name,
            $address->parent,
            $sessionId !== '' ? $sessionId : null,
        );

        if (Fs::isDir($page->pageDir)) {
            $r->error('Страница уже существует');

            return $r;
        }
        if (!Fs::isDir($page->parentDir)) {
            $r->error('Родительская папка не найдена: ' . $page->parentRelPath);

            return $r;
        }
        if (!is_file($page->parentPhpFile)) {
            $r->error('Родительская страница не найдена (нет файла ' . basename($page->parentPhpFile) . ')');

            return $r;
        }

        $namespace = $target->project->namespace . '\\' . $target->aliasName();
        $php = new PageCompiler()->compile($namespace, $page->classChain, []);

        $relFiles = [
            Fs::rel($page->phpFile, $this->paths->root()),
            Fs::rel($page->rulesFile, $this->paths->root()),
            Fs::rel($page->editorFile, $this->paths->root()),
            Fs::rel($page->parentRulesFile, $this->paths->root()),
        ];

        if ($context->isDryRun()) {
            $context->step('Dry-run: страница ' . $address->display() . ' будет создана');
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'page' => $address->display(),
                'target' => $page->targetRelPath,
                'files' => $relFiles,
                'dry_run' => true,
            ];

            return $r;
        }

        Fs::writeAtomic($page->phpFile, $php);
        $r->file(Fs::rel($page->phpFile, $this->paths->root()));

        PageRules::save($page->rulesFile, PageRules::defaults());
        $r->file(Fs::rel($page->rulesFile, $this->paths->root()));

        Fs::writeAtomic($page->editorFile, "[]\n");
        $r->file(Fs::rel($page->editorFile, $this->paths->root()));

        PageRules::save(
            $page->parentRulesFile,
            PageRules::withChild(PageRules::load($page->parentRulesFile), $address->name),
        );
        $r->file(Fs::rel($page->parentRulesFile, $this->paths->root()));

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'page' => $address->display(),
            'target' => $page->targetRelPath,
            'files' => $relFiles,
        ];

        $context->step('Страница создана', true, $page->targetRelPath);

        return $r;
    }
}
