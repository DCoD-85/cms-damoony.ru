<?php

declare(strict_types=1);

namespace Damoony\Cli\Db\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;

final class SchemaInitCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    public function name(): string
    {
        return 'db:schema:init';
    }

    public function description(): string
    {
        return 'Create a basic JSON schema for a new table';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        
        $domain = (string) ($args['domain'] ?? '');
        if ($domain === '' || str_contains($domain, '..')) {
            $r->error('Invalid or missing --domain');
            return $r;
        }
        
        $table = (string) ($args['table'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            $r->error('Invalid or missing --table (use lowercase a-z, 0-9, _)');
            return $r;
        }

        $domainDir = $this->paths->domain($domain);
        $dbDir = $domainDir . '/.db';
        $schemaFile = $dbDir . '/schema.json';

        // Создаем папки, если нет
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0o775, true);
        }

        // Читаем существующую схему или создаем новую
        $schema = [];
        if (is_file($schemaFile)) {
            $content = file_get_contents($schemaFile);
            $decoded = json_decode((string)$content, true);
            if (is_array($decoded)) {
                $schema = $decoded;
            }
        }

        if (isset($schema['tables'][$table])) {
            $r->error("Table '{$table}' already exists in schema.json");
            return $r;
        }

        // Добавляем базовую структуру
        $schema['tables'][$table] = [
            'columns' => [
                'id' => ['type' => 'int', 'primary' => true, 'auto_increment' => true],
                'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP']
            ]
        ];

        file_put_contents(
            $schemaFile,
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $r->file('app/' . $domain . '/.db/schema.json');
        $context->step("Table '{$table}' added to schema.json", true);

        return $r;
    }
}
