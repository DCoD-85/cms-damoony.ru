<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * Проверка AIUI-сессии (.sess_{tab}.{project}/.session.json).
 *
 * Контракт:
 *  - пустой sessionId — dev CLI-режим, разрешено всегда;
 *  - непустой — сессия обязана существовать, а permission-ключ (если задан)
 *    должен быть строго true.
 */
final class SessionGuard
{
    private const SESSION_RE = '/^[a-f0-9]{8,32}$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @throws RuntimeException
     */
    public function assertAllowed(string $sessionId, string $folder, string $permissionKey = ''): void
    {
        if ($sessionId === '') {
            return;
        }

        if (preg_match(self::SESSION_RE, $sessionId) !== 1) {
            throw new RuntimeException('Некорректный --session (8-32 hex символов)');
        }

        $sessionDir = $this->paths->sessionDir($sessionId, $folder);
        if (!is_dir($sessionDir)) {
            throw new RuntimeException('Сессия не найдена: ' . $sessionId);
        }

        if ($permissionKey === '') {
            return;
        }

        $metaFile = $sessionDir . '/.session.json';
        if (!is_file($metaFile)) {
            throw new RuntimeException('Метаданные сессии отсутствуют: .session.json');
        }

        $raw = @file_get_contents($metaFile);
        $meta = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($meta)) {
            throw new RuntimeException('Некорректный .session.json');
        }

        if (($meta['permissions'][$permissionKey] ?? false) !== true) {
            throw new RuntimeException(
                sprintf('Операция "%s" запрещена в этой сессии (.session.json)', $permissionKey),
            );
        }
    }

    /**
     * База проекта: production или изолированная сессия.
     */
    public function baseDir(string $folder, string $sessionId): string
    {
        if ($sessionId === '') {
            return rtrim($this->paths->domain($folder), '/');
        }

        return rtrim($this->paths->sessionDir($sessionId, $folder), '/');
    }
}
