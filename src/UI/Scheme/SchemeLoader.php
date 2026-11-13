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
        $file = null;

        if ($this->adminRole !== null) {
            $baseDir = $appRoot . '/.admin/.schemesglobal/';
            $roleDir = $appRoot . '/.admin/' . $this->adminRole . '/.schemesglobal/';

            $paths = [
                $roleDir . $schemeName . '.php',
                $roleDir . $domain->allias . '.php',
                $baseDir . $schemeName . '.php',
                $baseDir . $domain->allias . '.php',
            ];

            foreach ($paths as $p) {
                if (is_file($p)) { $file = $p; break; }
            }
        } else {
            $domainDir = $this->sessionId !== null
                ? '/.sess_' . $this->sessionId . '.' . $domain->real
                : '/' . $domain->real;

            $localDir = $appRoot . $domainDir . '/' . $domain->allias . '/.schemeslocal/';
            $baseDir = $appRoot . $domainDir . '/.schemesglobal/';

            // v4 Priority:
            // 1. useScheme() name in .schemeslocal
            // 2. alias.php in .schemeslocal
            // 3. useScheme() name in .schemesglobal
            // 4. alias.php in .schemesglobal
            // 5. www.php in .schemesglobal
            $paths = [
                $localDir . $schemeName . '.php',
                $localDir . $domain->allias . '.php',
                $baseDir . $schemeName . '.php',
                $baseDir . $domain->allias . '.php',
                $baseDir . 'www.php',
            ];

            foreach ($paths as $p) {
                if (is_file($p)) { $file = $p; break; }
            }
        }

        if ($file === null) {
            if (!Env::fromServer()->isDev()) {
                throw new RuntimeException('Scheme not found for alias: ' . $domain->allias . ' or scheme: ' . $schemeName);
            }
            error_log('Warning: scheme not found (' . $schemeName . ' | ' . $domain->allias . ') — using fallback (dev only)');
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
