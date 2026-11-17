<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Env;
use Damoony\Core\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    /** @return array{0: Logger, 1: mixed} */
    private function make(): array
    {
        $stream = fopen('php://memory', 'w+b');
        assert(is_resource($stream));
        return [new Logger($stream, Env::Test), $stream];
    }

    /**  */
    private function read(mixed $stream): array
    {
        assert(is_resource($stream));
        rewind($stream);
        $raw = stream_get_contents($stream);
        assert(is_string($raw));
        $lines = array_filter(explode("\n", trim($raw)));
        return array_map(fn (string $l) => json_decode($l, true), $lines);
    }

    public function testEmitsJsonLineWithFields(): void
    {
        [$logger, $stream] = $this->make();
        $logger->info('hello', ['k' => 'v']);

        $lines = $this->read($stream);
        self::assertCount(1, $lines);
        self::assertSame('info', $lines[0]['level']);
        self::assertSame('hello', $lines[0]['message']);
        self::assertSame('test', $lines[0]['env']);
        self::assertSame(['k' => 'v'], $lines[0]['context']);
        self::assertArrayHasKey('ts', $lines[0]);
    }

    public function testIncludesRequestIdChannelAndHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'damoony.ru';
        \Damoony\Core\Observability\RequestId::reset();
        \Damoony\Core\Observability\RequestId::init('0123456789abcdef');

        $stream = fopen('php://memory', 'w+b');
        assert(is_resource($stream));
        $logger = new Logger($stream, Env::Test, 'http');
        $logger->info('req');

        $lines = $this->read($stream);
        self::assertSame('http', $lines[0]['channel']);
        self::assertSame('0123456789abcdef', $lines[0]['request_id']);
        self::assertSame('damoony.ru', $lines[0]['host']);
        self::assertIsInt($lines[0]['pid']);

        unset($_SERVER['HTTP_HOST']);
        \Damoony\Core\Observability\RequestId::reset();
    }
    public function testAllLevels(): void
    {
        [$logger, $stream] = $this->make();
        $logger->emergency('e');
        $logger->alert('a');
        $logger->critical('c');
        $logger->error('er');
        $logger->warning('w');
        $logger->notice('n');
        $logger->info('i');
        $logger->debug('d');

        $levels = array_column($this->read($stream), 'level');
        self::assertSame(
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            $levels,
        );
    }

    public function testStringableMessage(): void
    {
        [$logger, $stream] = $this->make();
        $msg = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'obj';
            }
        };
        $logger->info($msg);
        $lines = $this->read($stream);
        self::assertSame('obj', $lines[0]['message']);
    }
}
