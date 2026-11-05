<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

use Damoony\Ai\Aicli\AicliClient;

final class ToolsCollector implements ToolsCollectorInterface
{
    public function __construct(private readonly AicliClient $client)
    {
    }

    /**
     * @return array{
     *     structure_ok: bool,
     *     structure_errors: list<string>,
     *     db_tables: list<string>,
     *     errors: list<string>
     * }
     */
    public function collect(string $domain, string $alias): array
    {
        $result = [
            'structure_ok' => true,
            'structure_errors' => [],
            'db_tables' => [],
            'errors' => [],
        ];

        $structure = $this->client->runJson(
            'structure:check --domain=' . escapeshellarg($domain)
        );
        $parsed = self::parseStructure($structure, $domain);
        $result['structure_ok'] = $parsed['structure_ok'];
        $result['structure_errors'] = $parsed['structure_errors'];
        if ($parsed['error'] !== '') {
            $result['errors'][] = $parsed['error'];
        }

        $schema = $this->client->runJson(
            'db:schema --domain=' . escapeshellarg($domain)
            . ' --alias=' . escapeshellarg($alias)
        );
        $parsed = self::parseSchema($schema);
        $result['db_tables'] = $parsed['db_tables'];
        if ($parsed['error'] !== '') {
            $result['errors'][] = $parsed['error'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $json
     * @return array{structure_ok: bool, structure_errors: list<string>, error: string}
     */
    public static function parseStructure(array $json, string $domain): array
    {
        $out = ['structure_ok' => true, 'structure_errors' => [], 'error' => ''];

        $steps = $json['steps'] ?? null;
        if (!is_array($steps)) {
            $out['error'] = 'structure:check: no steps in output';
            return $out;
        }

        // State machine: заголовок "Алиас: <domain> / <alias> ..." открывает секцию,
        // последующие indented-строки ("  ✗ ...") — её детали.
        $inOurDomain = false;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $msg = (string) ($step['message'] ?? '');
            $isOk = ($step['ok'] ?? true) !== false;

            if (preg_match('/^Алиас:\s+(\S+)\s+\//u', $msg, $m)) {
                $inOurDomain = ($m[1] === $domain);
            }

            if (!$isOk && $inOurDomain) {
                $out['structure_ok'] = false;
                $out['structure_errors'][] = trim($msg);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $json
     * @return array{db_tables: list<string>, error: string}
     */
    public static function parseSchema(array $json): array
    {
        $out = ['db_tables' => [], 'error' => ''];

        if (!($json['ok'] ?? false)) {
            $out['error'] = 'db:schema failed: ' . (string) ($json['error'] ?? 'unknown');
            return $out;
        }

        $tables = $json['data']['tables'] ?? null;
        if (!is_array($tables)) {
            $out['error'] = 'db:schema: no data.tables in output';
            return $out;
        }

        foreach ($tables as $t) {
            if (is_array($t) && isset($t['name']) && is_string($t['name'])) {
                $out['db_tables'][] = $t['name'];
            }
        }

        return $out;
    }
}
