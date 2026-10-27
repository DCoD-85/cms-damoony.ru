<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private array $backup = [];

    protected function setUp(): void
    {
        $this->backup = [
            'env' => $_ENV['APP_ENV'] ?? null,
            'server' => $_SERVER['APP_ENV'] ?? null,
        ];
        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->backup['env'] !== null) {
            $_ENV['APP_ENV'] = $this->backup['env'];
        } else {
            unset($_ENV['APP_ENV']);
        }
        if ($this->backup['server'] !== null) {
            $_SERVER['APP_ENV'] = $this->backup['server'];
        } else {
            unset($_SERVER['APP_ENV']);
        }
    }

    public function testDefaultIsProd(): void
    {
        self::assertSame(Env::Prod, Env::fromServer());
    }

    public function testFromEnv(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        self::assertSame(Env::Dev, Env::fromServer());
    }

    public function testFromServer(): void
    {
        $_SERVER['APP_ENV'] = 'test';
        self::assertSame(Env::Test, Env::fromServer());
    }

    public function testUnknownFallsBackToProd(): void
    {
        $_ENV['APP_ENV'] = 'weird';
        self::assertSame(Env::Prod, Env::fromServer());
    }

    public function testPredicates(): void
    {
        self::assertTrue(Env::Dev->isDev());
        self::assertTrue(Env::Test->isTest());
        self::assertTrue(Env::Staging->isStaging());
        self::assertTrue(Env::Prod->isProd());
        self::assertFalse(Env::Dev->isProd());
    }
}
