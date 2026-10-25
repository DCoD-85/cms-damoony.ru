<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class Context
{
    /** @var callable(string, bool, string): void */
    private $stepHandler;

    /** @param callable(string, bool, string): void $stepHandler */
    public function __construct(callable $stepHandler)
    {
        $this->stepHandler = $stepHandler;
    }

    public function step(string $message, bool $ok = true, string $detail = ''): void
    {
        ($this->stepHandler)($message, $ok, $detail);
    }
}