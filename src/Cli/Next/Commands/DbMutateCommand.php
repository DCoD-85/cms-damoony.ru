<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\ProviderInvoker;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\AuditProvider;
use Damoony\Data\Db\DataProvider\ProviderInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * db:mutate — write-вызов провайдера: whitelist, обязательный --force=1, audit_log.
 */
final class DbMutateCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const METHOD_RE = '/^[a-z][a-z0-9_]*$/';
    private const CORE_PREFIX = 'Damoony\\Data\\Db\\DataProvider\\';

    private const WRITE_METHODS = [
        'user' => ['create'],
        'news' => ['create', 'update', 'delete', 'publish'],
        'files' => ['create', 'update_filesize', 'increment_downloads', 'delete'],
        'form_submission' => ['delete', 'delete_all'],
    ];

    public function __construct(
        private readonly SiteConfig $site,
        private readonly ?Connection $db,
        private readonly ProviderInvoker $invoker,
    ) {
    }

    public function name(): string
    {
        return 'db:mutate';
    }

    public function description(): string
    {
        return 'Write-вызов провайдера (--force=1, audit_log)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alias = (string) ($args['alias'] ?? 'www');
        if (preg_match(self::ALIAS_RE, $alias) !== 1) {
            $r->error('Некорректный --alias');

            return $r;
        }
        if ($this->db === null) {
            $r->error('БД не сконфигурирована (config/engine.config → db)');

            return $r;
        }

        $provider = (string) ($args['provider'] ?? '');
        $class = $this->invoker->resolveClass($provider, $project->folder, $alias);
        if ($class === null) {
            $r->error(sprintf('Неизвестный провайдер: "%s"', $provider));

            return $r;
        }

        /** @var class-string $class */
        $method = (string) ($args['method'] ?? '');
        if (preg_match(self::METHOD_RE, $method) !== 1) {
            $r->error('Некорректный --method');

            return $r;
        }

        if (str_starts_with($class, self::CORE_PREFIX)) {
            $allowed = self::WRITE_METHODS[$provider] ?? [];
            if (!in_array($method, $allowed, true)) {
                $r->error(sprintf(
                    "Метод '%s' не в whitelist для '%s'. Разрешены: %s",
                    $method,
                    $provider,
                    implode(', ', $allowed),
                ));

                return $r;
            }
        }

        if (($args['force'] ?? '') !== '1') {
            $r->error('Write-операции требуют --force=1');

            return $r;
        }

        $params = $this->invoker->params($args);
        if ($params === null) {
            $r->error('--params или --params_b64 должны быть JSON-объектом');

            return $r;
        }

        $source = (string) ($args['source'] ?? 'cli');
        if (!in_array($source, ['cli', 'ai'], true)) {
            $source = 'cli';
        }
        $role = (string) ($args['role'] ?? '');

        $ref = new ReflectionClass($class);
        if (!$ref->hasMethod($method)) {
            $r->error(sprintf('Метод "%s" не найден в провайдере "%s"', $method, $provider));

            return $r;
        }

        $bound = $this->invoker->bind($ref->getMethod($method), $params);
        if (isset($bound['error'])) {
            $r->error((string) $bound['error']);

            return $r;
        }

        $providerSite = $this->invoker->providerConfig($project, $alias);

        try {
            /** @var ProviderInterface $instance */
            $instance = new $class($this->db, $providerSite, $project->folder, $alias);
            $result = $ref->getMethod($method)->invokeArgs($instance, $bound['args'] ?? []);
        } catch (Throwable $e) {
            $this->audit($r, $providerSite, $project->folder, $alias, $provider, $method, $params, false, $e->getMessage(), $source, $role);
            $r->error('Мутация не удалась: ' . $e->getMessage());

            return $r;
        }

        $this->audit($r, $providerSite, $project->folder, $alias, $provider, $method, $params, true, '', $source, $role);

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'provider' => $provider,
            'method' => $method,
            'result' => $result,
        ];

        $context->step(sprintf('Мутация %s::%s', $provider, $method), true);

        return $r;
    }

    /**
     * Аудит best-effort: не блокирует мутацию, если таблица audit_log не готова.
     *
     * @param array<string, mixed> $params
     */
    private function audit(
        Result $r,
        Config $providerSite,
        string $folder,
        string $alias,
        string $provider,
        string $method,
        array $params,
        bool $ok,
        string $error,
        string $source,
        string $role,
    ): void {
        if ($this->db === null) {
            return;
        }

        try {
            $audit = new AuditProvider($this->db, $providerSite, $folder, $alias);
            $audit->log($folder, $alias, $provider, $method, $params, $ok, $error, $source, $role);
        } catch (Throwable $e) {
            $r->warn('audit_log не записан: ' . $e->getMessage());
        }
    }
}
