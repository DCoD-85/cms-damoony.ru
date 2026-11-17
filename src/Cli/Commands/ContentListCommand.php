<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\ContentLayout;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * content:list для схемы domains_app.
 */
final class ContentListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly ContentLayout $layout,
    ) {
    }

    public function name(): string
    {
        return 'content:list';
    }

    public function description(): string
    {
        return 'Список контента проекта (/content/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $category = (string) ($args['category'] ?? '');
        $base = $this->paths->content($target->folder(), $target->aliasName());

        if (!Fs::isDir($base)) {
            $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'rows' => []];
            $context->step('Контента нет', true);

            return $r;
        }

        $categories = $category !== ''
            ? [$category]
            : array_values(array_filter(
                array_map('basename', glob($base . '/*', GLOB_ONLYDIR) ?: []),
                static fn (string $c): bool => $c !== '.trash' && !str_starts_with($c, '.'),
            ));
        sort($categories);

        $rows = [];
        foreach ($categories as $cat) {
            if (preg_match(ContentLayout::CATEGORY_RE, $cat) !== 1) {
                continue;
            }

            foreach (glob($base . '/' . $cat . '/*.meta.json') ?: [] as $metaFile) {
                $hash = basename($metaFile, '.meta.json');
                if (preg_match(ContentLayout::HASH_RE, $hash) !== 1) {
                    continue;
                }

                $meta = $this->layout->readMeta($metaFile) ?? [];
                $refs = is_array($meta['page_refs'] ?? null) ? $meta['page_refs'] : [];

                $rows[] = [
                    'category' => $cat,
                    'hash' => $hash,
                    'mime' => (string) ($meta['mime'] ?? ''),
                    'size' => (int) ($meta['size'] ?? 0),
                    'page_refs' => array_values(array_filter($refs, 'is_string')),
                ];
                $r->file($cat . '/' . $hash);
                $context->step(
                    sprintf(
                        '%-12s %s %8d B refs:%d',
                        $cat,
                        $hash,
                        (int) ($meta['size'] ?? 0),
                        count($refs),
                    ),
                    true,
                    $hash,
                );
            }
        }

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'rows' => $rows,
            'count' => count($rows),
        ];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
