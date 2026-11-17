<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\DataProvider\ProviderResolver;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * db:describe — сигнатура одного метода провайдера (read-only).
 */
final class DbDescribeCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const METHOD_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'db:describe';
    }

    public function description(): string
    {
        return 'Сигнатура метода провайдера (read-only)';
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

        $provider = (string) ($args['provider'] ?? '');
        $method = (string) ($args['method'] ?? '');

        if (preg_match(self::METHOD_RE, $method) !== 1) {
            $r->error('Некорректный --method');

            return $r;
        }

        $class = ProviderResolver::resolveClass($provider, $project->folder, $alias, $this->paths);
        if ($class === null) {
            $r->error(sprintf('Неизвестный провайдер: "%s"', $provider));

            return $r;
        }

        $ref = new ReflectionClass($class);

        if (!$ref->hasMethod($method)) {
            $r->error(sprintf('Метод "%s" не найден в провайдере "%s"', $method, $provider));

            return $r;
        }

        $target = $ref->getMethod($method);
        if ($target->getDeclaringClass()->getName() !== $ref->getName()) {
            $r->error(sprintf('Метод "%s" унаследован, а не объявлен в провайдере', $method));

            return $r;
        }
        if (!$target->isPublic()) {
            $r->error(sprintf('Метод "%s" не публичный', $method));

            return $r;
        }

        $params = [];
        foreach ($target->getParameters() as $parameter) {
            $params[] = [
                'name' => $parameter->getName(),
                'type' => $this->typeName($parameter->getType()),
                'required' => !$parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'provider' => $provider,
            'class' => $class,
            'method' => $method,
            'params' => $params,
            'return_type' => $this->typeName($target->getReturnType()),
            'doc' => $target->getDocComment() === false ? '' : $target->getDocComment(),
        ];

        $context->step(sprintf('Описание %s::%s', $provider, $method), true);

        return $r;
    }

    private function typeName(?object $type): string
    {
        return $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
    }
}
