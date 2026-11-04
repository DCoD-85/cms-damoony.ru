<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider\Template;

final class InsertTemplate implements TemplateInterface
{
    public static function compile(array $args): string
    {
        if (!isset($args['method_name']) || !preg_match('/^[a-z][a-z0-9_]*$/', (string) $args['method_name'])) {
            throw new \InvalidArgumentException('Template "insert" requires valid "method_name" (lowercase, a-z0-9_).');
        }
        $methodName = $args['method_name'];
        
        $params = $args['params'] ?? [];
        $fields = $args['fields'] ?? [];

        if (empty($fields)) {
            throw new \InvalidArgumentException('Template "insert" requires non-empty "fields" array.');
        }

        // 1. Signature
        $sig = [];
        $bindings = [];
        foreach ($params as $p) {
            $type = $p['type'] ?? 'string';
            $name = $p['name'];
            $sig[] = "{$type} \${$name}";
            $bindings[$name] = "\${$name}";
        }
        $signature = implode(', ', $sig);

        // 2. SQL Parts
        $fieldsStr = implode(', ', $fields);
        $placeholders = implode(', ', array_map(static fn(string $f): string => ':' . $f, $fields));

        // 3. Bindings array
        $bindingsArrayStr = "[\n";
        foreach ($bindings as $k => $v) {
            $bindingsArrayStr .= "            '{$k}' => {$v},\n";
        }
        $bindingsArrayStr .= "        ]";

        $sqlExpr = var_export("INSERT INTO ", true) . " . \$this->physicalTable() . " . var_export(" ({$fieldsStr}) VALUES ({$placeholders})", true);

        return <<<PHP
    public function {$methodName}({$signature}): int
    {
        \$this->query(
            {$sqlExpr},
            {$bindingsArrayStr}
        );

        return (int) \$this->conn->pdo()->lastInsertId();
    }
PHP;
    }
}
