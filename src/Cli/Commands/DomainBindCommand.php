<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Core\Settings\UnknownProjectException;
use Throwable;

/**
 * domain:bind — привязать дополнительный хост (миррор) к проекту.
 */
final class DomainBindCommand implements CommandInterface
{
    public function __construct(
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'domain:bind';
    }

    public function description(): string
    {
        return 'Привязать хост (миррор) к проекту';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $host = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $real = (string) ($args['real'] ?? '');

        if ($host === '' || $real === '') {
            $r->error('Укажите --domain=[host] и --real=[проект]');

            return $r;
        }
        if (!SiteConfig::isValidHost($host)) {
            $r->error('Некорректный хост');

            return $r;
        }
        if ($host === SiteConfig::normalizeHost($real)) {
            $r->error('Хост и проект совпадают (используйте domain:new)');

            return $r;
        }

        try {
            $project = $this->site->resolve($real);
        } catch (UnknownProjectException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $owner = $this->site->projectByHost($host);
        if ($owner !== null && $owner->key === $project->key) {
            $context->step(sprintf('Хост %s уже привязан к %s', $host, $project->key), true);

            return $r;
        }
        if ($owner !== null) {
            $r->error(sprintf('Хост %s уже принадлежит проекту %s', $host, $owner->key));

            return $r;
        }

        try {
            $this->writer->ensureMirror($project->key, $host);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = ['host' => $host, 'project' => $project->key];
        $context->step(sprintf('Хост %s привязан к %s', $host, $project->key), true);

        return $r;
    }
}
