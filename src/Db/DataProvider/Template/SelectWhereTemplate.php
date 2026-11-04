<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider\Template;

final class SelectWhereTemplate implements TemplateInterface
{
    public static function compile(array $args): string
    {
        $methodName = $args['method_name'] ?? 'custom_method';
        $returnType = $args['return_type'] ?? 'array';

        $params = $args['params'] ?? [];
        $fields = $args['fields'] ?? ['*'];
        $where = $args['where'] ?? [];
        $order = $args['order'] ?? [];
        $limitParam = $args['limit_param'] ?? null;

        // 1. Signature
        $sig = [];
        foreach ($params as $p) {
            $type = $p['type'] ?? 'string';
            $name = $p['name'];
            $default = array_key_exists('default', $p) ? ' = ' . var_export($p['default'], true) : '';
            $sig[] = "{$type} \${$name}{$default}";
        }
        $signature = implode(', ', $sig);

        // 2. SQL String
        $sqlSelect = implode(', ', $fields);
        $whereSql = [];
        $bindings = [];
        foreach ($where as $w) {
            $field = $w['field'];
            $op = $w['op'] ?? '=';
            if (isset($w['value'])) {
                $val = var_export($w['value'], true);
                $whereSql[] = "{$field} {$op} {$val}";
            } else {
                $param = $w['param'];
                $whereSql[] = "{$field} {$op} :{$param}";
                $bindings[$param] = "\${$param}";
            }
        }

        $orders = [];
        foreach ($order as $o) {
            $orders[] = $o['field'] . ' ' . ($o['direction'] ?? 'ASC');
        }

        $sqlString = "SELECT {$sqlSelect} FROM ' . \$this->physicalTable() . '";
        if (!empty($whereSql)) {
            $sqlString .= ' WHERE ' . implode(' AND ', $whereSql);
        }
        if (!empty($orders)) {
            $sqlString .= ' ORDER BY ' . implode(', ', $orders);
        }

        $returnStr = '';
        if ($limitParam !== null) {
            $sqlString .= " LIMIT ' . \${$limitParam}";
            $returnStr .= "        \${$limitParam} = max(1, min(100, \${$limitParam}));\n";
        } else {
            $sqlString .= "'";
        }

        $bindingsArrayStr = '[';
        foreach ($bindings as $k => $v) {
            $bindingsArrayStr .= "'{$k}' => {$v}, ";
        }
        $bindingsArrayStr .= ']';

        $returnStr .= "        \$rows = \$this->query(\n";
        $returnStr .= "            {$sqlString},\n";
        $returnStr .= "            {$bindingsArrayStr}\n";
        $returnStr .= "        )->fetchAll();\n\n";

        if ($returnType === 'array') {
            $returnStr .= "        \$out = [];\n";
            $returnStr .= "        foreach (\$rows as \$row) {\n";
            $returnStr .= "            if (is_array(\$row)) {\n";
            $returnStr .= "                \$out[] = \$row;\n";
            $returnStr .= "            }\n";
            $returnStr .= "        }\n";
            $returnStr .= "        return \$out;\n";
        } else {
            $returnStr .= "        \$row = \$rows[0] ?? [];\n";
            $returnStr .= "        return is_array(\$row) ? \$row : [];\n";
        }

        return <<<PHP
                /**
                 * @return array<string, mixed>
                 */
                public function {$methodName}({$signature}): array
                {
            {$returnStr}    }
            PHP;
    }
}
