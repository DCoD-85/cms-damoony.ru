<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use ErrorException;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    private mixed $prevHandler = null;

    protected function setUp(): void
    {
        $this->prevHandler = set_error_handler(fn () => false);
        restore_error_handler();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    private function make(Env $env = Env::Test): ErrorHandler
    {
        $stream = fopen('php://memory', 'w+b');
        $logger = new Logger($stream, $env);
        return new ErrorHandler($logger, $env);
    }

    public function testHandleErrorThrowsWhenReported(): void
    {
        $h = $this->make();
        $this->expectException(ErrorException::class);
        $h->handleError(E_WARNING, 'boom', __FILE__, __LINE__);
    }

    public function testHandleErrorReturnsFalseWhenSuppressed(): void
    {
        $h = $this->make();
        $prev = error_reporting(0);
        try {
            self::assertFalse($h->handleError(E_WARNING, 'quiet', __FILE__, __LINE__));
        } finally {
            error_reporting($prev);
        }
    }

    public function testHandleShutdownIgnoresNonFatal(): void
    {
        $h = $this->make();
        // simply must not throw
        $h->handleShutdown();
        self::assertTrue(true);
    }
}
