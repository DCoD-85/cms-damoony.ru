<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Db\DataProvider\BasicCrudProvider;
use Damoony\Data\Db\DataProvider\ProviderResolver;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

/**
 * db:methods — публичные бизнес-методы провайдера (read-only, Reflection).
 */
final class DbMethodsCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const CONTRACT_METHODS = ['table', 'physicalTable', 'domain', 'alias'];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'db:methods';
    }

    public function description(): string
    {
        return 'Методы провайдера (read-only)';
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
        $class = ProviderResolver::resolveClass($provider, $project->folder, $alias, $this->paths);
        if ($class === null) {
            $r->error(sprintf('Неизвестный провайдер: "%s" (см. app/{folder}/{alias}/.dbprovider/)', $provider));

            return $r;
        }

        $ref = new ReflectionClass($class);

        $methods = [];
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $declarer = $method->getDeclaringClass()->getName();
            if (!in_array($declarer, [$ref->getName(), BasicCrudProvider::class], true)) {
                continue;
            }
            if (in_array($method->getName(), self::CONTRACT_METHODS, true)) {
                continue;
            }

            $methods[] = [
                'name' => $method->getName(),
                'params' => $this->params($method),
                'return_type' => $this->returnType($method),
                'summary' => $this->summary($method),
            ];
        }

        usort($methods, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $r->data = [
            'project' => $project->key,
            'alias' => $alias,
            'provider' => $provider,
            'class' => $class,
            'methods' => $methods,
        ];

        $context->step(sprintf('Провайдер "%s": %d методов', $provider, count($methods)), true);

        return $r;
    }

    /**
     * @return list<array{name: string, type: string, required: bool, default: mixed}>
     */
    private function params(ReflectionMethod $method): array
    {
        $out = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $out[] = [
                'name' => $parameter->getName(),
                'type' => $type instanceof ReflectionNamedType ? $type->getName() : 'mixed',
                'required' => !$parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $out;
    }

    private function returnType(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
    }

    private function summary(ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return '';
        }

        foreach (preg_split('/\R/', $doc) ?: [] as $line) {
            $clean = trim($line);
            $clean = trim((string) preg_replace('#^/\*+#', '', $clean));
            $clean = trim((string) preg_replace('#\*/$#', '', $clean));
            $clean = trim((string) preg_replace('#^\*+\s?#', '', $clean));

            if ($clean === '' || str_starts_with($clean, '@')) {
                continue;
            }

            return $clean;
        }

        return '';
    }
}
