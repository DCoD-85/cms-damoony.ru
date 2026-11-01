<?php

declare(strict_types=1);

namespace Damoony\Ainet\ImageSearch;

final class ProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $hint = '',
        public readonly string $provider = '',
    ) {
        parent::__construct($message);
    }
}
