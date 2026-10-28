<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Diagnostics\StructureChecker;
use Damoony\Files\Paths;

final class StructureCheckCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Config $site,
    ) {
    }

    public function name(): string
    {
        return 'structure:check';
    }

    public function description(): string
    {
        return 'Проверить целостность структуры доменов и алиасов';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $checker = new StructureChecker($this->paths, $this->site);

        $allaliases = $this->site->getByKey(['alliases_real'], []);
        $totalIssues = 0;

        if (is_array($allaliases)) {
            foreach ($allaliases as $domain => $aliases) {
                if (!is_array($aliases)) {
                    continue;
                }

                foreach ($aliases as $allias => $mapped) {
                    if ($allias === '') {
                        continue;
                    }

                    $issues = $checker->checkAllias((string)$domain, (string)$allias);
                    if ($issues !== []) {
                        $totalIssues += count($issues);
                        $context->step("Алиас: {$domain} / {$allias}", false);
                        foreach ($issues as $issue) {
                            $context->step('  - ' . $issue['message'], false);
                        }
                    } else {
                        $context->step("Алиас: {$domain} / {$allias} — OK", true);
                    }
                }
            }
        }

        if ($totalIssues > 0) {
            $r->error("Найдено проблем: {$totalIssues}");
        } else {
            $context->step('Все проверки пройдены успешно!');
        }

        return $r;
    }
}
