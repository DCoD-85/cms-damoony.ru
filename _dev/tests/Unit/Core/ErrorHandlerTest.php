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
    public function testDevExceptionRendersEscapedElements(): void
    {
        $h = $this->make(Env::Dev);

        ob_start();
        $h->handleException(new \RuntimeException('boom <script>'));
        $out = (string) ob_get_clean();

        self::assertStringContainsString('<div class="error error-500">', $out);
        self::assertStringContainsString('RuntimeException', $out);
        self::assertStringContainsString('boom &lt;script&gt;', $out);
        self::assertStringNotContainsString('boom <script>', $out);
        self::assertStringContainsString('Request ID:', $out);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testProdExceptionIsPlainText(): void
    {
        $h = $this->make(Env::Prod);

        ob_start();
        $h->handleException(new \RuntimeException('boom'));
        $out = (string) ob_get_clean();

        self::assertStringContainsString('Internal Server Error', $out);
        self::assertStringContainsString('Request ID:', $out);
        self::assertStringNotContainsString('<div', $out);
        self::assertStringNotContainsString('boom', $out);
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
