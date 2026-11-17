<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Throwable;

/**
 * domain:unbind — снять дополнительный хост (миррор) с проекта.
 */
final class DomainUnbindCommand implements CommandInterface
{
    public function __construct(
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'domain:unbind';
    }

    public function description(): string
    {
        return 'Снять хост (миррор) с проекта';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $host = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));

        if ($host === '') {
            $r->error('Укажите --domain=[host]');

            return $r;
        }

        $project = $this->site->projectByHost($host);
        if ($project === null) {
            $context->step(sprintf('Хост %s и так не привязан', $host), true);

            return $r;
        }
        if ($project->key === $host) {
            $r->error(sprintf('Нельзя отвязать основной хост проекта %s — используйте domain:remove', $host));

            return $r;
        }

        try {
            $removed = $this->writer->removeMirror($project->key, $host);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        if (!$removed) {
            $r->error(sprintf('Хост %s не найден у проекта %s', $host, $project->key));

            return $r;
        }

        $r->data = ['host' => $host, 'project' => $project->key];
        $context->step(sprintf('Хост %s отвязан от %s', $host, $project->key), true);

        return $r;
    }
}
