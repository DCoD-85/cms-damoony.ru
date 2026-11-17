<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PageAddress;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PagePath;
use RuntimeException;

/**
 * page:read для схемы domains_app.
 */
final class PageReadCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'page:read';
    }

    public function description(): string
    {
        return 'Прочитать PHP-код страницы';
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

        $page = PagePath::resolve($this->paths, $target->folder(), $target->aliasName(), $address->name, $address->parent);

        if (!Fs::isFile($page->phpFile)) {
            $r->error('Страница не найдена: ' . $page->phpFile);

            return $r;
        }

        $code = file_get_contents($page->phpFile);
        if ($code === false) {
            $r->error('Не удалось прочитать файл');

            return $r;
        }

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'name' => $address->display(),
            'path' => $page->phpFile,
            'code' => $code,
        ];

        $context->step('Страница прочитана: ' . $page->phpFile, true, $address->display());

        return $r;
    }
}
