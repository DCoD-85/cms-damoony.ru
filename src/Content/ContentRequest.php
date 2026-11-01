<?php

declare(strict_types=1);

namespace Damoony\Content;

/**
 * Разобранный запрос /content/<domain>/<allias>/<category>/<hash>.<ext>.
 * Иммутабельный.
 */
final class ContentRequest
{
    public function __construct(
        public readonly string $domain,
        public readonly string $allias,
        public readonly string $category,
        public readonly string $hash,
        public readonly string $ext,
        public readonly string $originalUri,
    ) {
    }
}
