<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Db\DataProvider\AuditProvider;
use Damoony\Db\DataProvider\ProviderInterface;
use Damoony\Db\DataProvider\ProviderResolver;
use Damoony\Files\Paths;

final class DbMutateCommand implements CommandInterface
{
    /**
     * Write-методы (whitelist). Требуют --force.
     *
     * @var array<string, list<string>>
     */
    private const WRITE_METHODS = [
        'user' => ['create'],
        'news' => ['create', 'update', 'delete', 'publish'],
        'files' => ['create', 'update_filesize', 'increment_downloads', 'delete'],
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly Config $site,
        private readonly Connection $db,
    ) {
    }

    public function name(): string
    {
        return 'db:mutate';
    }

    public function description(): string
    {
        return 'Invoke a write provider method (whitelist, --force required, audit logged)';
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

        $allowed = self::WRITE_METHODS[$provider] ?? [];
        if (!in_array($method, $allowed, true)) {
            $r->error(
                "Method '{$method}' is not in write whitelist for '{$provider}'."
                . ' Allowed: ' . implode(', ', $allowed),
            );

            return $r;
        }

        if (!isset($args['force']) || $args['force'] !== '1') {
            $r->error('Write operations require --force=1');

            return $r;
        }

        $params = $this->parseParams($args);
        if (!is_array($params)) {
            $r->error('--params or --params_b64 must be a valid JSON object.');

            return $r;
        }

        $source = (string) ($args['source'] ?? 'cli');
        if (!in_array($source, ['cli', 'ai'], true)) {
            $source = 'cli';
        }
        $role = (string) ($args['role'] ?? '');

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

        $audit = new AuditProvider($this->db, $this->site, $domain, $alias);

        try {
            /** @var ProviderInterface $instance */
            $instance = new $class($this->db, $this->site, $domain, $alias);
            $result = $refMethod->invokeArgs($instance, $ordered);
        } catch (\Throwable $e) {
            $this->tryAudit($audit, $r, $domain, $alias, $provider, $method, $params, false, $e->getMessage(), $source, $role);
            $r->error('Mutate failed: ' . $e->getMessage());

            return $r;
        }

        $this->tryAudit($audit, $r, $domain, $alias, $provider, $method, $params, true, '', $source, $role);

        $r->data = [
            'provider' => $provider,
            'method' => $method,
            'domain' => $domain,
            'alias' => $alias,
            'result' => $result,
        ];
        $context->step("Mutated {$provider}::{$method}", true);

        return $r;
    }

    /**
     * Audit — best-effort (не блокирует mutate, если audit_log не мигрирован).
     *
     * @param array<string, mixed> $params
     */
    private function tryAudit(
        AuditProvider $audit,
        Result $r,
        string $domain,
        string $alias,
        string $provider,
        string $method,
        array $params,
        bool $ok,
        string $error,
        string $source,
        string $role,
    ): void {
        try {
            $audit->log($domain, $alias, $provider, $method, $params, $ok, $error, $source, $role);
        } catch (\Throwable $e) {
            $r->warn('audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null
     */
    private function parseParams(array $args): ?array
    {
        $b64 = $args['params_b64'] ?? '';
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                return null;
            }
            $parsed = json_decode($decoded, true);

            return is_array($parsed) ? $parsed : null;
        }

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
