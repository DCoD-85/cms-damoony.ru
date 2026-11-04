<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Db\DataProvider\ProviderInterface;
use Damoony\Db\DataProvider\ProviderResolver;
use Damoony\Files\Paths;

final class DbCallCommand implements CommandInterface
{
    /**
     * Read-only методы (whitelist). Write-методы — db:mutate (Phase 3.2).
     *
     * @var array<string, list<string>>
     */
    private const READ_METHODS = [
        'user' => ['by_login', 'by_id', 'exists'],
        'news' => ['recent', 'by_slug', 'by_id', 'search_like'],
        'files' => ['all_active', 'by_slug', 'by_id'],
        'audit' => ['recent', 'by_entity'],
        'form_submission' => ['recent'],
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly Config $site,
        private readonly Connection $db,
    ) {
    }

    public function name(): string
    {
        return 'db:call';
    }

    public function description(): string
    {
        return 'Invoke a read-only provider method (whitelist, args validated by reflection)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        if ($domain === ''
            || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)
            || str_contains($domain, '..')) {
            $r->error('Invalid or missing --domain.');

            return $r;
        }

        $alias = $args['alias'] ?? 'www';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
            $r->error('Invalid --alias.');

            return $r;
        }

        $provider = $args['provider'] ?? '';
        $class = ProviderResolver::resolveClass($provider, $domain, $alias, $this->paths);
        if ($class === null) {
            $r->error('Unknown provider: "' . $provider . '". Allowed: ' . implode(', ', ['user', 'news', 'files', 'audit']));

            return $r;
        }

        $method = $args['method'] ?? '';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $method)) {
            $r->error('Invalid or missing --method.');

            return $r;
        }

        $allowed = self::READ_METHODS[$provider] ?? [];
        if (str_starts_with($class, 'Damoony\\Db\\DataProvider\\') && !in_array($method, $allowed, true)) {
            $r->error(
                "Method '{$method}' is not in whitelist for '{$provider}'."
                . ' Allowed core methods: ' . implode(', ', $allowed),
            );

            return $r;
        }

        $params = $this->parseParams($args);
        if (!is_array($params)) {
            $r->error('--params or --params_b64 must be a valid JSON object.');

            return $r;
        }

        $ref = new \ReflectionClass($class);
        $refMethod = $ref->getMethod($method);
        $methodParams = $refMethod->getParameters();

        $ordered = [];
        if (count($methodParams) === 1 && $methodParams[0]->getType() instanceof \ReflectionNamedType && $methodParams[0]->getType()->getName() === 'array') {
            $ordered[] = $params;
        } else {
            $unknown = array_diff(array_keys($params), array_map(
                static fn (\ReflectionParameter $p): string => $p->getName(),
                $methodParams,
            ));
            if ($unknown !== []) {
                $r->error('Unknown params: ' . implode(', ', $unknown));
                return $r;
            }

            foreach ($methodParams as $p) {
                $name = $p->getName();
                $hasKey = array_key_exists($name, $params);

                if (!$hasKey && $p->isOptional()) {
                    $ordered[] = $p->getDefaultValue();
                    continue;
                }
                if (!$hasKey) {
                    $r->error("Missing required param '{$name}'.");
                    return $r;
                }

                $value = $params[$name];
                $type = $p->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

                $cast = $this->castValue($value, $typeName);
                if ($cast === null) {
                    $r->error("Param '{$name}' expects {$typeName}, got " . gettype($value));
                    return $r;
                }

                $ordered[] = $cast;
            }
        }

        try {
            /** @var ProviderInterface $instance */
            $instance = new $class($this->db, $this->site, $domain, $alias);
            $result = $refMethod->invokeArgs($instance, $ordered);
        } catch (\Throwable $e) {
            $r->error('Call failed: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'provider' => $provider,
            'method' => $method,
            'domain' => $domain,
            'alias' => $alias,
            'result' => $result,
        ];
        if (is_array($result)) {
            // array_is_list возвращает true для списков (recent), false для одной записи (by_slug)
            $r->data['row_count'] = array_is_list($result) ? count($result) : (empty($result) ? 0 : 1);
        }
        $context->step("Called {$provider}::{$method}", true);

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null null = parse failure
     */
    private function parseParams(array $args): ?array
    {
        // base64 — предпочтительный путь (безопасно для Windows escapeshellarg)
        $b64 = $args['params_b64'] ?? '';
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                return null;
            }
            $parsed = json_decode($decoded, true);

            return is_array($parsed) ? $parsed : null;
        }

        // fallback: plain --params (работает в bash без escapeshellarg)
        $json = $args['params'] ?? '{}';
        $parsed = json_decode($json, true);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @return string|int|bool|array<mixed>|null null = type mismatch
     */
    private function castValue(mixed $value, string $typeName): string|int|bool|array|null
    {
        return match ($typeName) {
            'string' => is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : null),
            'int' => is_int($value) ? $value : (is_numeric($value) ? (int) $value : null),
            'bool' => is_bool($value) ? $value : null,
            'array' => is_array($value) ? $value : null,
            default => null,
        };
    }
}
