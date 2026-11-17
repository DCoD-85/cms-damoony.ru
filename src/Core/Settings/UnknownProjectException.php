<?php

declare(strict_types=1);

namespace Damoony\Core\Settings;

use RuntimeException;

/**
 * Запрошенный проект отсутствует в site.config.
 */
final class UnknownProjectException extends RuntimeException
{
    public static function forInput(string $input): self
    {
        return new self("site.config: проект/хост \"{$input}\" не найден в domains_app");
    }
}
