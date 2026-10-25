<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Security;

use Damoony\Security\Csrf;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
            /** @var array<string, mixed> */
            public array $data = [];

            public function start(): void
            {
            }

            public function isStarted(): bool
            {
                return true;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->data[$key]);
            }

            public function regenerate(): void
            {
            }

            public function destroy(): void
            {
                $this->data = [];
            }
        };
    }

    public function testTokenIsStable(): void
    {
        $s = $this->session();
        $c = new Csrf($s);

        $t1 = $c->token();
        $t2 = $c->token();
        self::assertSame($t1, $t2);
        self::assertSame(64, strlen($t1));
    }

    public function testFieldContainsToken(): void
    {
        $c = new Csrf($this->session());
        $token = $c->token();
        self::assertStringContainsString('name="_csrf"', $c->field());
        self::assertStringContainsString('value="' . $token . '"', $c->field());
    }

    public function testCheck(): void
    {
        $c = new Csrf($this->session());
        $token = $c->token();

        self::assertTrue($c->check(['_csrf' => $token]));
        self::assertFalse($c->check(['_csrf' => 'wrong']));
        self::assertFalse($c->check([]));
    }

    public function testCheckEmptyTokenInSession(): void
    {
        $c = new Csrf($this->session());
        self::assertFalse($c->check(['_csrf' => 'anything']));
    }
}