<?php

declare(strict_types=1);

namespace Damoony\Core\Routing;

use Damoony\Core\Domain\DomainContext;

final class FileRouter implements RouterInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rulesCache = [];

    public function __construct(
        private readonly string $appRoot,
        private readonly RulesMatcher $matcher,
    ) {
    }

    public function match(string $path, DomainContext $domain, ?string $sessionId = null, ?string $adminRole = null): RouteMatch
    {
        $segments = $this->segments($path);

        if ($adminRole !== null) {
            $base = $this->appRoot . '/app/.admin/' . $adminRole . '/algoritm';
        } elseif ($sessionId !== null) {
            $base = $this->appRoot . '/app/.sess_' . $sessionId . '.' . $domain->real . '/' . $domain->allias . '/algoritm';
        } else {
            $base = $this->appRoot . '/app/' . $domain->real . '/' . $domain->allias . '/algoritm';
        }

        if (!is_dir($base)) {
            return RouteMatch::notFound();
        }

        $rootRules = $this->loadRules($base . '/algoritm.rules.json');
        if ($rootRules === null) {
            return RouteMatch::notFound();
        }

        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];
        $authRequired = false;

        // Пустой путь (/) → синтетический сегмент '', чтобы сработал '' в available
        $segments = $segments === [] ? [''] : $segments;

        foreach ($segments as $i => $segment) {
            $rules = $i === 0
                ? $rootRules
                : $this->loadRules($base . '/' . $pathRel . '/' . $classChain . '.rules.json');

            if ($rules === null) {
                return RouteMatch::notFound();
            }

            $authRequired = $authRequired || $this->matcher->requiresAuth($rules);

            $matched = $this->matcher->matchSegment($segment, $rules);
            if ($matched === null) {
                return RouteMatch::notFound();
            }

            [$target, $segArgs] = $matched;

            // Пустой сегмент ('') в available = стоп, вызываем default() текущего узла
            if ($target === '') {
                break;
            }

            // Если сработала регулярка (переменная)
            if ($segArgs !== []) {
                $args = array_merge($args, $segArgs);

                // Если target - это реальная папка (например, 'lang'), обновляем путь
                $checkDir = $base . '/' . ($pathRel === '' ? '' : $pathRel . '/') . $target;
                if (is_dir($checkDir)) {
                    $pathRel = $pathRel === '' ? $target : $pathRel . '/' . $target;
                    $classChain .= '_' . $target;
                }
                continue;
            }

            $pathRel = $pathRel === '' ? $target : $pathRel . '/' . $target;
            $classChain .= '_' . $target;

            $candidatePhp = $base . '/' . $pathRel . '/' . $classChain . '.php';
            if (!is_file($candidatePhp)) {
                if ($adminRole !== null) {
                    return RouteMatch::found($classChain, $pathRel, $args, $authRequired);
                }
                return RouteMatch::notFound();
            }
        }

        $leafRulesFile = $base . ($pathRel === '' ? '' : '/' . $pathRel) . '/' . $classChain . '.rules.json';
        $leafRules = $this->loadRules($leafRulesFile) ?? [];
        $authRequired = $authRequired || $this->matcher->requiresAuth($leafRules);

        return RouteMatch::found($classChain, $pathRel, $args, $authRequired);
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /** @return array<string, mixed>|null */
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

        return $this->rulesCache[$file] = $decoded;
    }
}
