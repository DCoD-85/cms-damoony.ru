<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Application;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private function make(Env $env = Env::Test): Application
    {
        $stream = fopen('php://memory', 'w+b');
        $logger = new Logger($stream, $env);
        $container = new Container();
        $config = Config::fromArray(['app' => ['name' => 'test']]);

        return new Application($container, $env, $config, $logger, new ErrorHandler($logger, $env));
    }

    public function testAccessors(): void
    {
        $app = $this->make(Env::Dev);

        self::assertSame(Env::Dev, $app->env());
        self::assertTrue($app->env()->isDev());
        self::assertSame('test', $app->config()->get('app.name'));
        self::assertInstanceOf(Container::class, $app->container());
        self::assertInstanceOf(Logger::class, $app->logger());
        self::assertInstanceOf(ErrorHandler::class, $app->errorHandler());
    }

    public function testBootIsIdempotent(): void
    {
        $app = $this->make();
        $app->boot();
        $app->boot();
        self::assertTrue(true);
    }
}