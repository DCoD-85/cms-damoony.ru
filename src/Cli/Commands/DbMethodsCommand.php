<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\DataProvider\ProviderResolver;

final class DbMethodsCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    /**
     * Контракт ProviderInterface — не бизнес-методы, не показываем AI.
     *
     * @var list<string>
     */
    private const CONTRACT_METHODS = [
        'name', 'table', 'physicalTable', 'domain', 'alias',
    ];

    public function name(): string
    {
        return 'db:methods';
    }

    public function description(): string
    {
        return 'List business methods of a provider (read-only)';
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

        $ref = new \ReflectionClass($class);

        $methods = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }
            if (in_array($m->getName(), self::CONTRACT_METHODS, true)) {
                continue;
            }

            $methods[] = [
                'name' => $m->getName(),
                'params' => $this->describeParams($m),
                'return_type' => $this->describeReturn($m),
                'summary' => $this->docSummary($m),
            ];
        }

        usort($methods, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $r->data = [
            'provider' => $provider,
            'class' => $class,
            'domain' => $domain,
            'alias' => $alias,
            'methods' => $methods,
        ];
        $context->step(sprintf('Provider "%s": %d methods', $provider, count($methods)), true);

        return $r;
    }

    /**
     * @return list<array{name: string, type: string, required: bool, default: mixed}>
     */
    private function describeParams(\ReflectionMethod $m): array
    {
        $out = [];
        foreach ($m->getParameters() as $p) {
            $type = $p->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

            $entry = [
                'name' => $p->getName(),
                'type' => $typeName,
                'required' => !$p->isOptional(),
                'default' => null,
            ];
            if ($p->isDefaultValueAvailable()) {
                $entry['default'] = $p->getDefaultValue();
            }
            $out[] = $entry;
        }

        return $out;
    }

    private function describeReturn(\ReflectionMethod $m): string
    {
        $rt = $m->getReturnType();
        if ($rt instanceof \ReflectionNamedType) {
            return $rt->getName();
        }

        return 'mixed';
    }

    private function docSummary(\ReflectionMethod $m): string
    {
        $doc = $m->getDocComment();
        if ($doc === false) {
            return '';
        }

        $lines = preg_split('/\R/', $doc) ?: [];
        foreach ($lines as $line) {
            $clean = trim($line);
            $clean = (string) preg_replace('#^/\*+#', '', $clean);
            $clean = (string) preg_replace('#\*/$#', '', $clean);
            $clean = trim((string) preg_replace('#^\*+\s?#', '', $clean));

            if ($clean === '' || str_starts_with($clean, '@')) {
                continue;
            }

            return $clean;
        }

        return '';
    }
}
