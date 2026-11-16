<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Stubs;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;

/**
 * @internal
 */
final class FakeCommand implements CommandInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $description = 'Fake',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function run(array $args, Context $context): Result
    {
        return new Result();
    }
}
