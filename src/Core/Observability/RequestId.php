<?php

declare(strict_types=1);

namespace Damoony\Core\Observability;

/**
 * Идентификатор запроса: связывает запись в логе, ответ и алерт.
 *
 * Генерируется в точке входа; входящий `X-Request-Id` принимается только
 * если совпадает с форматом (иначе подменяем своим — доверять заголовку нельзя).
 */
final class RequestId
{
    private const FORMAT = '/^[a-f0-9]{8,32}$/';

    private static ?string $current = null;

    public static function init(?string $incoming = null): string
    {
        if ($incoming !== null && preg_match(self::FORMAT, $incoming) === 1) {
            self::$current = $incoming;

            return $incoming;
        }

        self::$current ??= bin2hex(random_bytes(8));

        return self::$current;
    }

    public static function initFromGlobals(): string
    {
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;

        return self::init(is_string($incoming) ? $incoming : null);
    }

    public static function get(): string
    {
        return self::$current ?? self::init();
    }

    /** Только для тестов. */
    public static function reset(): void
    {
        self::$current = null;
    }

    public static function isValid(string $id): bool
    {
        return preg_match(self::FORMAT, $id) === 1;
    }
}
