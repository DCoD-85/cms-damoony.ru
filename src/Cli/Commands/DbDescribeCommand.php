<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Db\DataProvider\FilesProvider;
use Damoony\Db\DataProvider\NewsProvider;
use Damoony\Db\DataProvider\UserProvider;

final class DbDescribeCommand implements CommandInterface
{
    /** @var array<string, class-string> */
    private const PROVIDERS = [
        'user' => UserProvider::class,
        'news' => NewsProvider::class,
        'files' => FilesProvider::class,
    ];

    public function name(): string
    {
        return 'db:describe';
    }

    public function description(): string
    {
        return 'Describe a provider method: params, return type, summary (read-only)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $provider = $args['provider'] ?? '';
        $method = $args['method'] ?? '';

        $class = self::PROVIDERS[$provider] ?? null;
        if ($class === null) {
            $r->error('Unknown provider: "' . $provider . '". Allowed: ' . implode(', ', array_keys(self::PROVIDERS)));

            return $r;
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $method)) {
            $r->error('Invalid or missing --method.');

            return $r;
        }

        $ref = new \ReflectionClass($class);

        if (!$ref->hasMethod($method)) {
            $r->error("Method '{$method}' not found in provider '{$provider}'.");

            return $r;
        }

        $m = $ref->getMethod($method);
        if ($m->getDeclaringClass()->getName() !== $ref->getName()) {
            $r->error("Method '{$method}' is inherited, not defined in provider '{$provider}'.");

            return $r;
        }

        if (!$m->isPublic()) {
            $r->error("Method '{$method}' is not public.");

            return $r;
        }

        $params = [];
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
            $params[] = $entry;
        }

        $rt = $m->getReturnType();
        $returnType = $rt instanceof \ReflectionNamedType ? $rt->getName() : 'mixed';

        $r->data = [
            'provider' => $provider,
            'class' => $class,
            'method' => $method,
            'params' => $params,
            'return_type' => $returnType,
            'doc' => $m->getDocComment() === false ? '' : $m->getDocComment(),
        ];
        $context->step("Described {$provider}::{$method}", true);

        return $r;
    }
}
