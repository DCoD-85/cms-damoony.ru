<?php

declare(strict_types=1);

namespace Damoony\Admin;

use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Session\SessionInterface;

final class Auth implements AuthInterface
{
    private const MAX_FAILS = 5;
    private const BAN_SECONDS = 900;
    private const DELAY_AFTER = 3;
    private const SESSION_KEY = 'admin_login';

    public function __construct(
        private readonly Config $adminConfig,
        private readonly SessionInterface $session,
        private readonly string $failsFile,
        private readonly string $ip,
    ) {
    }

    public function attempt(string $login, string $password): bool
    {
        if ($this->isBanned()) {
            return false;
        }

        $expectedLogin = (string) $this->adminConfig->get('login', 'admin');
        $hash = (string) $this->adminConfig->get('password_hash', '');

        $ok = $hash !== ''
            && hash_equals($expectedLogin, $login)
            && password_verify($password, $hash);

        if (!$ok) {
            $this->recordFail();
            if ($this->failsCount() >= self::DELAY_AFTER) {
                usleep(500_000);
            }
            return false;
        }

        $this->resetFails();

        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $login);

        return true;
    }

    public function isLoggedIn(): bool
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $login = $this->session->get(self::SESSION_KEY);
        return is_string($login) && $login !== '';
    }

    public function login(): string
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $login = $this->session->get(self::SESSION_KEY);
        return is_string($login) ? $login : '';
    }

    public function logout(): void
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $this->session->remove(self::SESSION_KEY);
    }

    /** @return array<string, array{count: int, last: int, banned_until: int}> */
    private function loadFails(): array
    {
        if (!Fs::isFile($this->failsFile)) {
            return [];
        }
        $raw = @file_get_contents($this->failsFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $ip => $entry) {
            if (!is_string($ip) || !is_array($entry)) {
                continue;
            }
            $out[$ip] = [
                'count' => (int) ($entry['count'] ?? 0),
                'last' => (int) ($entry['last'] ?? 0),
                'banned_until' => (int) ($entry['banned_until'] ?? 0),
            ];
        }

        return $out;
    }

    /** @param array<string, array{count: int, last: int, banned_until: int}> $data */
    private function saveFails(array $data): void
    {
        $now = time();
        foreach ($data as $ip => $entry) {
            if ($entry['count'] === 0 && $now - $entry['last'] > 7 * 86400) {
                unset($data[$ip]);
            }
        }

        Fs::writeAtomic($this->failsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function recordFail(): void
    {
        $data = $this->loadFails();
        $entry = $data[$this->ip] ?? ['count' => 0, 'last' => 0, 'banned_until' => 0];
        $entry['count']++;
        $entry['last'] = time();
        if ($entry['count'] >= self::MAX_FAILS) {
            $entry['banned_until'] = time() + self::BAN_SECONDS;
        }
        $data[$this->ip] = $entry;
        $this->saveFails($data);
    }

    private function resetFails(): void
    {
        $data = $this->loadFails();
        unset($data[$this->ip]);
        $this->saveFails($data);
    }

    private function failsCount(): int
    {
        return $this->loadFails()[$this->ip]['count'] ?? 0;
    }

    private function isBanned(): bool
    {
        $entry = $this->loadFails()[$this->ip] ?? null;
        return $entry !== null && $entry['banned_until'] > time();
    }
}
