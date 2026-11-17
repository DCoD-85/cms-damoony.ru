<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Diagnostics\StructureChecker;
use RuntimeException;

/**
 * structure:check — целостность структуры (3 файла, схемы, блоки, филлеры).
 *
 * Severity-aware: error → ok=false (exit 1), warning → информативно.
 */
final class StructureCheckCommand implements CommandInterface
{
    public function __construct(
        private readonly StructureChecker $checker,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'structure:check';
    }

    public function description(): string
    {
        return 'Проверить целостность структуры (3 файла, схемы, блоки)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $aliasFilter = (string) ($args['allias'] ?? '');

        $projects = [];
        if ($domain !== '') {
            try {
                $projects[] = $this->site->resolve($domain);
            } catch (RuntimeException $e) {
                $r->error($e->getMessage());

                return $r;
            }
        } else {
            $projects = array_values($this->site->projects());
        }

        if ($projects === []) {
            $r->data = ['errors' => [], 'warnings' => [], 'checked_aliases' => 0];
            $context->step('Проектов нет — проверять нечего', true);

            return $r;
        }

        $errors = [];
        $warnings = [];
        $checked = 0;

        foreach ($projects as $project) {
            $aliases = $aliasFilter !== '' ? [$aliasFilter] : $project->aliasNames();
            if ($aliases === []) {
                $aliases = [$project->defaultAlias];
            }

            foreach ($aliases as $alias) {
                $checked++;

                foreach ($this->checker->checkProjectAllias($project, $alias) as $issue) {
                    $line = sprintf('%s/%s: %s', $project->key, $alias, $issue['message']);

                    if ($issue['level'] === 'error') {
                        $errors[] = $line;

                        continue;
                    }

                    $warnings[] = $line;
                }
            }
        }

        foreach ($errors as $error) {
            $r->error($error);
            $context->step('✗ ' . $error, false, $error);
        }
        foreach ($warnings as $warning) {
            $r->warn($warning);
            $context->step('⚠ ' . $warning, true, $warning);
        }

        $r->data = [
            'errors' => $errors,
            'warnings' => $warnings,
            'checked_aliases' => $checked,
        ];

        if ($errors !== []) {
            $context->step(sprintf('Ошибок: %d, предупреждений: %d', count($errors), count($warnings)), false);

            return $r;
        }

        $context->step(
            $warnings === []
                ? 'Все проверки пройдены успешно'
                : sprintf('Критичных ошибок нет, предупреждений: %d', count($warnings)),
            true,
        );

        return $r;
    }
}
