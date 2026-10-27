<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin;

use Damoony\Admin\Auth;
use Damoony\Core\Config;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private string $failsFile;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/damoony_auth_' . bin2hex(random_bytes(4));
        mkdir($dir, 0o777, true);
        $this->failsFile = $dir . '/fails.json';
    }

    private function session(): SessionInterface
    {
        return new class () implements SessionInterface {
            /** @var array<string, mixed> */
            public array $data = [];
            public bool $started = false;

            public function start(): void
            {
                $this->started = true;
            }

            public function isStarted(): bool
            {
                return $this->started;
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

    private function adminConfig(): Config
    {
        return Config::fromArray([
            'login' => 'admin',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);
    }

    private function auth(SessionInterface $session, string $ip = '10.0.0.1'): Auth
    {
        return new Auth($this->adminConfig(), $session, $this->failsFile, $ip);
    }

    public function testSuccessfulLogin(): void
    {
        $s = $this->session();
        $a = $this->auth($s);

        self::assertFalse($a->isLoggedIn());
        self::assertTrue($a->attempt('admin', 'secret'));
        self::assertTrue($a->isLoggedIn());
        self::assertSame('admin', $a->login());
    }

    public function testWrongPassword(): void
    {
        $a = $this->auth($this->session());
        self::assertFalse($a->attempt('admin', 'wrong'));
        self::assertFalse($a->isLoggedIn());
    }

    public function testWrongLogin(): void
    {
        $a = $this->auth($this->session());
        self::assertFalse($a->attempt('root', 'secret'));
    }

    public function testEmptyHashFails(): void
    {
        $cfg = Config::fromArray(['login' => 'admin', 'password_hash' => '']);
        $a = new Auth($cfg, $this->session(), $this->failsFile, '10.0.0.1');
        self::assertFalse($a->attempt('admin', 'anything'));
    }

    public function testBanAfterMaxFails(): void
    {
        $s = $this->session();
        $a = $this->auth($s, '10.0.0.2');

        for ($i = 0; $i < 5; $i++) {
            $a->attempt('admin', 'wrong');
        }

        // even correct password doesn't work while banned
        self::assertFalse($a->attempt('admin', 'secret'));
    }

    public function testLogout(): void
    {
        $s = $this->session();
        $a = $this->auth($s);
        $a->attempt('admin', 'secret');
        self::assertTrue($a->isLoggedIn());

        $a->logout();
        self::assertFalse($a->isLoggedIn());
    }
}
