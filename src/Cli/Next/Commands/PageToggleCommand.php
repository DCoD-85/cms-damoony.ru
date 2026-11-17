<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\PageAddress;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PagePath;
use Damoony\UI\Page\PageRules;
use RuntimeException;

/**
 * page:toggle для схемы domains_app: auth / search / default в .rules.json.
 */
final class PageToggleCommand implements CommandInterface
{
    private const KEYS = ['auth', 'search', 'default'];

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'page:toggle';
    }

    public function description(): string
    {
        return 'Переключить флаг страницы (auth/search/default)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $key = (string) ($args['key'] ?? '');
        if (!in_array($key, self::KEYS, true)) {
            $r->error('Недопустимый ключ: ' . $key);

            return $r;
        }

        $value = ($args['value'] ?? '1') === '0' ? false : true;

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

        $page = PagePath::resolve($this->paths, $target->folder(), $target->aliasName(), $address->name, $address->parent);

        if (!Fs::isFile($page->phpFile)) {
            $r->error('Страница не найдена');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(
                'Dry-run: флаг ' . $key . '=' . ($value ? '1' : '0') . ' для ' . $address->display() . ' будет обновлён',
            );
            $r->data = [
                'page' => $address->display(),
                'key' => $key,
                'value' => $value,
                'dry_run' => true,
            ];

            return $r;
        }

        $merged = PageRules::withDefaults(PageRules::load($page->rulesFile));
        $merged[$key] = $value;

        PageRules::save($page->rulesFile, $merged);

        $relPath = Fs::rel($page->rulesFile, $this->paths->root());
        $r->file($relPath);

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'page' => $address->display(),
            'key' => $key,
            'value' => $value,
            'file' => $relPath,
        ];

        $context->step('Флаг обновлён', true, "{$key}=" . ($value ? '1' : '0'));

        return $r;
    }
}
