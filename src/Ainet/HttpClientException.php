<?php

declare(strict_types=1);

namespace Damoony\Ainet;

final class HttpClientException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $hint = '',
    ) {
        parent::__construct($message);
    }
}
