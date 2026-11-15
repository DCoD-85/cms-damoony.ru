<?php

declare(strict_types=1);

namespace Damoony\Cli\Db\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;

final class SchemaAddColumnCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    public function name(): string
    {
        return 'db:schema:add-column';
    }

    public function description(): string
    {
        return 'Add a column to a table in schema.json';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $table = (string) ($args['table'] ?? '');
        $colName = (string) ($args['name'] ?? '');
        $type = (string) ($args['type'] ?? 'varchar');
        $length = (int) ($args['length'] ?? 0);
        $isNull = isset($args['null']) && $args['null'] === '1';
        $default = $args['default'] ?? null;

        if ($domain === '' || str_contains($domain, '..')) {
            $r->error('Invalid or missing --domain');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table) || !preg_match('/^[a-z][a-z0-9_]*$/', $colName)) {
            $r->error('Invalid --table or --name (use lowercase a-z, 0-9, _)');
            return $r;
        }

        $allowedTypes = ['int', 'varchar', 'text', 'timestamp', 'boolean', 'decimal'];
        if (!in_array($type, $allowedTypes, true)) {
            $r->error('Invalid --type. Allowed: ' . implode(', ', $allowedTypes));
            return $r;
        }

        $schemaFile = $this->paths->domain($domain) . '/.db/schema.json';
        if (!is_file($schemaFile)) {
            $r->error('schema.json not found. Run db:schema:init first.');
            return $r;
        }

        $schema = json_decode((string)file_get_contents($schemaFile), true);
        if (!isset($schema['tables'][$table])) {
            $r->error("Table '{$table}' not found in schema.json");
            return $r;
        }

        if (isset($schema['tables'][$table]['columns'][$colName])) {
            $r->error("Column '{$colName}' already exists in table '{$table}'");
            return $r;
        }

        $colDef = ['type' => $type];
        if ($length > 0) {
            $colDef['length'] = $length;
        }
        if ($isNull) {
            $colDef['null'] = true;
        }
        if ($default !== null) {
            $colDef['default'] = $default;
        }

        $schema['tables'][$table]['columns'][$colName] = $colDef;

        file_put_contents(
            $schemaFile,
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );

        $r->file('app/' . $domain . '/.db/schema.json');
        $context->step("Column '{$colName}' added to table '{$table}'", true);

        return $r;
    }
}
