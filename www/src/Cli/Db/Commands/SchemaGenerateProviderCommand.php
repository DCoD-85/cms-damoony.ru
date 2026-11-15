<?php

declare(strict_types=1);

namespace Damoony\Cli\Db\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class SchemaGenerateProviderCommand implements CommandInterface
{
    use ResolvesRealDomain;

    public function __construct(
        private readonly Paths $paths,
        private readonly Config $site,
    ) {
    }

    public function name(): string
    {
        return 'db:schema:generate-provider';
    }

    public function description(): string
    {
        return 'Generate PHP DataProvider from schema.json';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $table = (string) ($args['table'] ?? '');

        if ($domain === '' || str_contains($domain, '..')) {
            $r->error('Invalid or missing --domain');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            $r->error('Invalid or missing --table');
            return $r;
        }

        try {
            $realDomain = $this->resolveRealDomain($this->paths, $domain);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $schemaFile = $this->paths->dbGlobal($realDomain) . '/schema.json';
        if (!is_file($schemaFile)) {
            $r->error('schema.json not found.');
            return $r;
        }

        $schema = json_decode((string)file_get_contents($schemaFile), true);
        if (!isset($schema['tables'][$table])) {
            $r->error("Table '{$table}' not found in schema.json");
            return $r;
        }

        // Получаем неймспейс домена из конфига (по realDomain)
        $ns = $this->site->getByKey(['domains_namespace', $realDomain], str_replace(['.', '-'], '_', $realDomain));
        if (!is_string($ns)) {
            $ns = str_replace(['.', '-'], '_', $realDomain);
        }

        // Алиас по умолчанию
        $alias = (string) ($args['alias'] ?? 'www');

        // Собираем writable колонки (все, кроме id, created_at, updated_at)
        $writable = [];
        foreach ($schema['tables'][$table]['columns'] as $colName => $colDef) {
            if (in_array($colName, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $writable[] = $colName;
        }

        $writableStr = implode(', ', array_map(fn ($c) => "'{$c}'", $writable));
        if ($writableStr === '') {
            $writableStr = '// empty';
        }

        $className = ucfirst($table) . 'Provider';

        $phpCode = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$ns}\\{$alias};

            use Damoony\\Data\\Db\\DataProvider\\BasicCrudProvider;

            final class {$className} extends BasicCrudProvider
            {
                public function name(): string
                {
                    return '{$table}';
                }

                public function table(): string
                {
                    return '{$table}';
                }

                protected function writable(): array
                {
                    return [
                        {$writableStr}
                    ];
                }
            }
            PHP;

        // Сохраняем провайдер в app/{realDomain}/{alias}/.dbprovider/
        $providerDir = $this->paths->allias($realDomain, $alias) . '/.dbprovider';
        if (!is_dir($providerDir)) {
            mkdir($providerDir, 0o775, true);
        }

        $filepath = $providerDir . '/' . $className . '.php';
        file_put_contents($filepath, $phpCode . "\n");

        $r->file('app/' . $realDomain . '/' . $alias . '/.dbprovider/' . $className . '.php');
        $context->step("Provider generated: {$className}", true);

        return $r;
    }
}
