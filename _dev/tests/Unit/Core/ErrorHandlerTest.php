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
        parent::tearDown();
    }

    private function make(Env $env = Env::Test): ErrorHandler
    {
        $stream = fopen('php://memory', 'w+b');
        $logger = new Logger($stream, $env);
        return new ErrorHandler($logger, $env);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testHandleErrorThrowsWhenReported(): void
    {
        $h = $this->make();
        $prevReporting = error_reporting(E_ALL);
        try {
            $this->expectException(ErrorException::class);
            $h->handleError(E_WARNING, 'boom', __FILE__, __LINE__);
        } finally {
            error_reporting($prevReporting);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testHandleShutdownIgnoresNonFatal(): void
    {
        $h = $this->make();
        $h->handleShutdown();
        self::assertTrue(true);
    }
}
