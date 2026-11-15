<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands\Concerns;

use Damoony\Core\Files\Paths;

/**
 * Проверка permission-флагов сессии AIUI (.sess_{tab}.{domain}/.session.json).
 *
 * Контракт:
 *  - $sessionId === '' — dev CLI-режим, разрешено всегда.
 *  - $sessionId !== '' — сессия обязана существовать, и permission-ключ
 *    в .session.json['permissions'] должен быть строго true.
 */
trait EnsuresSessionPermission
{
    /**
     * @throws \RuntimeException
     */
    protected function ensureSessionPermission(
        Paths $paths,
        string $sessionId,
        string $realDomain,
        string $permKey,
    ): void {
        if ($sessionId === '') {
            return;
        }

        if (!preg_match('/^[a-f0-9]{8,32}$/', $sessionId)) {
            throw new \RuntimeException('Некорректный --session (8-32 hex символов)');
        }

        $sessionDir = $paths->sessionDir($sessionId, $realDomain);
        if (!is_dir($sessionDir)) {
            throw new \RuntimeException('Сессия не найдена: ' . $sessionId);
        }

        $metaFile = $sessionDir . '/.session.json';
        if (!is_file($metaFile)) {
            throw new \RuntimeException('Метаданные сессии отсутствуют: .session.json');
        }

        $raw = @file_get_contents($metaFile);
        $meta = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($meta)) {
            throw new \RuntimeException('Некорректный .session.json');
        }

        $perm = $meta['permissions'][$permKey] ?? false;
        if ($perm !== true) {
            throw new \RuntimeException(
                'Операция "' . $permKey . '" запрещена в этой сессии (.session.json)',
            );
        }
    }

    /**
     * База домена: production или .sess_{tab}.{domain}.
     *
     * @throws \RuntimeException
     */
    protected function resolveDomainBase(Paths $paths, string $realDomain, string $sessionId): string
    {
        if ($sessionId === '') {
            return rtrim($paths->domain($realDomain), '/');
        }

        return rtrim($paths->sessionDir($sessionId, $realDomain), '/');
    }
}
