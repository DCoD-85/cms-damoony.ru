<?php

declare(strict_types=1);

namespace Damoony\Routing;

use Damoony\Domain\DomainContext;
use RuntimeException;

final class FileRouter implements RouterInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rulesCache = [];

    public function __construct(
        private readonly string $appRoot,
        private readonly RulesMatcher $matcher,
    ) {
    }

    public function match(string $path, DomainContext $domain): RouteMatch
    {
        $segments = $this->segments($path);
        $ns = $domain->logic;
        $domainReal = $domain->real;
        $allias = $domain->allias;

        $base = $this->appRoot . '/app/' . $ns . '/domain/' . $domainReal . '/allias/' . $allias . '/algoritm';
        if (!is_dir($base)) {
            return RouteMatch::notFound();
        }

        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];

        foreach ($segments as $segment) {
            $rulesFile = $base . ($pathRel === '' ? '' : '/' . $pathRel) . '/.rules.json';
            $rules = $this->loadRules($rulesFile);
            if ($rules === null) {
                return RouteMatch::notFound();
            }

            if ($this->matcher->requiresAuth($rules)) {
                // auth check is delegated to caller via domain context; not available here
            }

            $matched = $this->matcher->matchSegment($segment, $rules);
            if ($matched === null) {
                return RouteMatch::notFound();
            }

            [$target, $segArgs] = $matched;
            $pathRel = $pathRel === '' ? $target : $pathRel . '/' . $target;
            $classChain = $classChain . '_' . $target;
            $args = array_merge($args, $segArgs);

            if (!is_dir($base . '/' . $pathRel) && !is_file($base . '/' . $pathRel . '.php')) {
                return RouteMatch::notFound();
            }
        }

        return RouteMatch::found($classChain, $pathRel, $args);
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadRules(string $file): ?array
    {
        if (isset($this->rulesCache[$file])) {
            return $this->rulesCache[$file];
        }

        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->rulesCache[$file] = $decoded;
    }
}