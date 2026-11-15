<?php

declare(strict_types=1);

namespace Damoony\Cli;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param array<string, string> $args
     */
    public function run(array $args, Context $context): Result;
}
