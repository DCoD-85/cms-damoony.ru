<?php

declare(strict_types=1);

namespace Damoony\Cli\Db\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class SchemaGenerateProviderCommand implements CommandInterface
{
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

        $schemaFile = $this->paths->domain($domain) . '/.db/schema.json';
        if (!is_file($schemaFile)) {
            $r->error('schema.json not found.');
            return $r;
        }

        $schema = json_decode((string)file_get_contents($schemaFile), true);
        if (!isset($schema['tables'][$table])) {
            $r->error("Table '{$table}' not found in schema.json");
            return $r;
        }

        // Получаем неймспейс домена из конфига
        $ns = $this->site->getByKey(['domains_namespace', $domain], str_replace(['.', '-'], '_', $domain));
        if (!is_string($ns)) {
            $ns = str_replace(['.', '-'], '_', $domain);
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

        // Сохраняем провайдер в app/{domain}/{alias}/.dataprovider/
        $providerDir = $this->paths->allias($domain, $alias) . '/.dataprovider';
        if (!is_dir($providerDir)) {
            mkdir($providerDir, 0o775, true);
        }

        $filepath = $providerDir . '/' . $className . '.php';
        file_put_contents($filepath, $phpCode . "\n");

        $r->file('app/' . $domain . '/' . $alias . '/.dataprovider/' . $className . '.php');
        $context->step("Provider generated: {$className}", true);

        return $r;
    }
}
