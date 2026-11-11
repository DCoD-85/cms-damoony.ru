<?php

declare(strict_types=1);

namespace Damoony\UI\Scheme;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Env;
use Damoony\Core\Files\Paths;
use RuntimeException;

final class SchemeLoader
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(
        private readonly Paths $paths,
        private readonly ?string $sessionId = null,
        private readonly ?string $adminRole = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(DomainContext $domain, string $schemeName = 'www'): array
    {
        $appRoot = rtrim($this->paths->root(), '/') . '/app';

        if ($this->adminRole !== null) {
            // admin = домен .admin + алиас {role}
            // приоритет: domain-level .admin/.alliasscheme → role-level .admin/{role}/.alliasscheme
            $baseDir = $appRoot . '/.admin/.alliasscheme/';
            $roleDir = $appRoot . '/.admin/' . $this->adminRole . '/.alliasscheme/';
        } else {
            $domainDir = $this->sessionId !== null
                ? '/.sess_' . $this->sessionId . '.' . $domain->real
                : '/' . $domain->real;
            $baseDir = $appRoot . $domainDir . '/.alliasscheme/';
        }

        if ($this->adminRole !== null) {
            // admin: ищем в role-level (alias override), затем в domain-level
            $file = $roleDir . $domain->allias . '.php';
            if (!is_file($file)) {
                $file = $roleDir . $schemeName . '.php';
            }
            if (!is_file($file)) {
                $file = $baseDir . $domain->allias . '.php';
            }
            if (!is_file($file)) {
                $file = $baseDir . $schemeName . '.php';
            }
        } else {
            // 1. Приоритет: схема алиаса (например, docs.php)
            $file = $baseDir . $domain->allias . '.php';

            // 2. Если нет — берем переданную схему (www.php)
            if (!is_file($file)) {
                $file = $baseDir . $schemeName . '.php';
            }
        }

        if (!is_file($file)) {
            if (!Env::fromServer()->isDev()) {
                throw new RuntimeException('Scheme not found: ' . $file);
            }

            error_log('Warning: scheme not found: ' . $file . ' — using fallback (dev only)');

            return [
                'tag' => 'div',
                'class' => 'layout',
                'children' => [
                    ['tag' => 'main', 'slot' => 'center'],
                ],
            ];
        }

        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }

        $data = require $file;
        if (!is_array($data)) {
            throw new RuntimeException('Invalid scheme format: ' . $file);
        }

        return $this->cache[$file] = $data;
    }
}
