<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI;

use Damoony\UI\Slot;
use PHPUnit\Framework\TestCase;

final class SlotTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $putenv = getenv('APP_ENV');
        $this->envBackup = [
            'server' => $_SERVER['APP_ENV'] ?? null,
            'env' => $_ENV['APP_ENV'] ?? null,
            'putenv' => $putenv === false ? null : $putenv,
        ];
    }

    protected function tearDown(): void
    {
        if ($this->envBackup['server'] === null) {
            unset($_SERVER['APP_ENV']);
        } else {
            $_SERVER['APP_ENV'] = $this->envBackup['server'];
        }
        if ($this->envBackup['env'] === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $this->envBackup['env'];
        }
        if ($this->envBackup['putenv'] === null) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->envBackup['putenv']);
        }
    }

    private function setEnv(string $env): void
    {
        $_ENV['APP_ENV'] = $env;
        $_SERVER['APP_ENV'] = $env;
        putenv('APP_ENV=' . $env);
    }

    public function testReleaseReturnsMarkerInDev(): void
    {
        $this->setEnv('dev');

        $slot = new Slot('header');

        $output = $slot->release();

        self::assertStringContainsString('UNRESOLVED SLOT', $output);
        self::assertStringContainsString('header', $output);
    }

    public function testReleaseReturnsEmptyInProd(): void
    {
        $this->setEnv('prod');

        $slot = new Slot('header');

        self::assertSame('', $slot->release());
    }
}
