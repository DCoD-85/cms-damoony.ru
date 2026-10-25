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
        $base = $this->appRoot . '/app/' . $ns . '/domain/' . $domain->real . '/allias/' . $domain->allias . '/algoritm';
        if (!is_dir($base)) {
            return RouteMatch::notFound();
        }

        $rootRules = $this->loadRules($base . '/.rules.json');
        if ($rootRules === null) {
            return RouteMatch::notFound();
        }

        if ($segments === []) {
            return RouteMatch::found('algoritm', '');
        }

        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];

        foreach ($segments as $i => $segment) {
            $rules = $i === 0
                ? $rootRules
                : $this->loadRules($base . '/' . $pathRel . '/.rules.json');
            if ($rules === null) {
                return RouteMatch::notFound();
            }

            $matched = $this->matcher->matchSegment($segment, $rules);
            if ($matched === null) {
                return RouteMatch::notFound();
            }

            [$target, $segArgs] = $matched;
            $pathRel = $pathRel === '' ? $target : $pathRel . '/' . $target;
            $classChain .= '_' . $target;
            $args = array_merge($args, $segArgs);

            $candidateDir = $base . '/' . $pathRel;
            $candidatePhp = $candidateDir . '/' . $classChain . '.php';
            if (!is_dir($candidateDir) && !is_file($candidatePhp)) {
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