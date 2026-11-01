<?php

declare(strict_types=1);

namespace Damoony\Ainet;

final class HttpTransportException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $curlErrno = 0,
    ) {
        parent::__construct($message);
    }
}
