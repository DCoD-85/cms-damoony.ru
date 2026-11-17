<?php

declare(strict_types=1);

namespace Damoony\Cli\Identity;

use Damoony\Core\Files\Fs;
use RuntimeException;

/**
 * Идентификатор владельца CLI: var/cli.identity (0600).
 *
 * Пока это единственная «аутентификация»: один человек/владелец. Роли и
 * токены — отдельный шаг, когда появится второй клиент (приложение).
 */
final class IdentityStore
{
    public function __construct(private readonly string $root)
    {
    }

    public function path(): string
    {
        return $this->root . '/var/cli.identity';
    }

    /**
     * @return array{user: string, created_at: int, secret: string}|null
     */
    public function load(): ?array
    {
        $file = $this->path();
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['user']) || !is_string($data['user']) || $data['user'] === '') {
            return null;
        }

        return [
            'user' => $data['user'],
            'created_at' => (int) ($data['created_at'] ?? 0),
            'secret' => (string) ($data['secret'] ?? ''),
        ];
    }

    /**
     *
     * @throws RuntimeException
     *
     * @return array{user: string, created_at: int, secret: string}
     */
    public function create(string $user, bool $force = false): array
    {
        if (is_file($this->path()) && !$force) {
            throw new RuntimeException('Идентификатор уже существует: ' . $this->path() . ' (используйте --force)');
        }

        $identity = [
            'user' => $user,
            'created_at' => time(),
            'secret' => bin2hex(random_bytes(16)),
        ];

        Fs::mkdir(dirname($this->path()));
        Fs::writeAtomic(
            $this->path(),
            json_encode($identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
        @chmod($this->path(), 0o600);

        return $identity;
    }

    /**
     * На Unix дополнительно проверяем, что файл принадлежит текущему пользователю.
     *
     * @throws RuntimeException
     */
    public function assertOwnedByCurrentUser(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !is_file($this->path())) {
            return;
        }

        $owner = @fileowner($this->path());
        if ($owner === false) {
            return;
        }

        if ($owner !== posix_geteuid()) {
            throw new RuntimeException(
                'Идентификатор CLI принадлежит другому пользователю: ' . $this->path(),
            );
        }
    }
}
