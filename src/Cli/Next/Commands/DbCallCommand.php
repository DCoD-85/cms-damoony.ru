<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\ProviderInvoker;
use Damoony\Cli\Result;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\ProviderInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * db:call — read-only вызов метода провайдера (whitelist ядра по имени провайдера).
 */
final class DbCallCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const METHOD_RE = '/^[a-z][a-z0-9_]*$/';
    private const CORE_PREFIX = 'Damoony\\Data\\Db\\DataProvider\\';

    private const READ_METHODS = [
        'user' => ['by_login', 'by_id', 'exists'],
        'news' => ['recent', 'by_slug', 'by_id', 'search_like'],
        'files' => ['all_active', 'by_slug', 'by_id'],
        'audit' => ['recent', 'by_entity'],
        'form_submission' => ['recent', 'by_form'],
    ];

    public function __construct(
        private readonly SiteConfig $site,
        private readonly ?Connection $db,
        private readonly ProviderInvoker $invoker,
    ) {
    }

    public function name(): string
    {
        return 'db:call';
    }

    public function description(): string
    {
        return 'Read-only вызов метода провайдера (whitelist)';
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
            $r->error(sprintf('Неизвестный провайдер: "%s" (см. app/{folder}/{alias}/.dbprovider/)', $provider));

            return $r;
        }

        /** @var class-string $class */
        $method = (string) ($args['method'] ?? '');
        if (preg_match(self::METHOD_RE, $method) !== 1) {
            $r->error('Некорректный --method');

            return $r;
        }

        if (str_starts_with($class, self::CORE_PREFIX)) {
            $allowed = self::READ_METHODS[$provider] ?? [];
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

        $params = $this->invoker->params($args);
        if ($params === null) {
            $r->error('--params или --params_b64 должны быть JSON-объектом');

            return $r;
        }

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

        try {
            $prefix = $project->storagePrefix($alias);

            /** @var ProviderInterface $instance */
            $instance = new $class($this->db, $prefix, $project->folder, $alias);
            $result = $ref->getMethod($method)->invokeArgs($instance, $bound['args'] ?? []);
        } catch (Throwable $e) {
            $r->error('Вызов не удался: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'provider' => $provider,
            'method' => $method,
            'result' => $result,
        ];

        if (is_array($result)) {
            $r->data['row_count'] = array_is_list($result) ? count($result) : 1;
        }

        $context->step(sprintf('Вызов %s::%s', $provider, $method), true);

        return $r;
    }
}
