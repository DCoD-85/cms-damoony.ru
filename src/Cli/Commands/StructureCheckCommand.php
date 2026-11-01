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
        $totalErrors = 0;
        $totalWarnings = 0;

        if (is_array($allaliases)) {
            foreach ($allaliases as $domain => $aliases) {
                if (!is_array($aliases)) {
                    continue;
                }

                foreach ($aliases as $allias => $mapped) {
                    if ($allias === '') {
                        continue;
                    }

                    $issues = $checker->checkAllias((string) $domain, (string) $mapped);

                    if ($issues === []) {
                        $context->step("Алиас: {$domain} / {$mapped} — OK", true);
                        continue;
                    }

                    $errors = array_filter($issues, static fn ($i) => $i['level'] === 'error');
                    $warnings = array_filter($issues, static fn ($i) => $i['level'] !== 'error');

                    $totalErrors += count($errors);
                    $totalWarnings += count($warnings);

                    $hasError = $errors !== [];
                    $mark = $hasError ? '✗' : '⚠';
                    $context->step("Алиас: {$domain} / {$mapped} {$mark}", !$hasError);

                    foreach ($issues as $issue) {
                        $issueMark = $issue['level'] === 'error' ? '✗' : '⚠';
                        $context->step("  {$issueMark} {$issue['message']}", $issue['level'] !== 'error');
                    }
                }
            }
        }

        if ($totalErrors > 0) {
            $msg = "Найдено ошибок: {$totalErrors}";
            if ($totalWarnings > 0) {
                $msg .= ", предупреждений: {$totalWarnings}";
            }
            $r->error($msg);
        } elseif ($totalWarnings > 0) {
            $context->step("Все критичные проверки пройдены, предупреждений: {$totalWarnings}");
        } else {
            $context->step('Все проверки пройдены успешно!');
        }

        return $r;
    }
}
