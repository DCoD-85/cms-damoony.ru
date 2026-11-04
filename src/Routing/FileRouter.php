<?php

declare(strict_types=1);

namespace Damoony\Routing;

use Damoony\Domain\DomainContext;

final class FileRouter implements RouterInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rulesCache = [];

    public function __construct(
        private readonly string $appRoot,
        private readonly RulesMatcher $matcher,
    ) {
    }

    public function match(string $path, DomainContext $domain, ?string $sessionId = null): RouteMatch
    {
        $segments = $this->segments($path);

        if ($sessionId !== null) {
            $base = $this->appRoot . '/app/.sess_' . $sessionId . '.' . $domain->real . '/' . $domain->allias . '/algoritm';
        } else {
            $base = $this->appRoot . '/app/' . $domain->real . '/' . $domain->allias . '/algoritm';
        }

        if (!is_dir($base)) {
            return RouteMatch::notFound();
        }

        // Модель: класс N хранит свои правила в algoritm/algoritm.rules.json,
        // класс N+M — в algoritm/<pathRel>/algoritm_<pathRel>.rules.json
        $rootRules = $this->loadRules($base . '/algoritm.rules.json');
        if ($rootRules === null) {
            return RouteMatch::notFound();
        }

        if ($segments === []) {
            return RouteMatch::found('algoritm', '', [], $this->matcher->requiresAuth($rootRules));
        }

        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];

        foreach ($segments as $i => $segment) {
            $rules = $i === 0
                ? $rootRules
                : $this->loadRules($base . '/' . $pathRel . '/' . $classChain . '.rules.json');

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

            $candidatePhp = $base . '/' . $pathRel . '/' . $classChain . '.php';
            if (!is_file($candidatePhp)) {
                return RouteMatch::notFound();
            }
        }

        // Листовые правила = правила последнего класса
        $leafRules = $this->loadRules($base . '/' . $pathRel . '/' . $classChain . '.rules.json') ?? [];
        $auth = $this->matcher->requiresAuth($leafRules);

        return RouteMatch::found($classChain, $pathRel, $args, $auth);
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
