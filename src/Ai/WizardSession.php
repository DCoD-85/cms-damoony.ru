<?php

declare(strict_types=1);

namespace Damoony\Ai;

use Damoony\Files\Fs;
use Damoony\Files\Paths;

/**
 * Хранилище состояния сессии Wizard.
 *
 * Чистый DTO. Не содержит бизнес-логики (валидация доменов, сборка страниц),
 * только управление жизненным циклом файла сессии и доступ к данным.
 */
final class WizardSession
{
    public const STAGE_INIT = 'init';
    public const STAGE_DOMAIN = 'domain';
    public const STAGE_ALLIAS = 'allias';
    public const STAGE_PLAN = 'plan';
    public const STAGE_CONTENT = 'content';
    public const STAGE_SKIN = 'skin';
    public const STAGE_READY = 'ready';
    public const STAGE_COMMITTED = 'committed';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private readonly string $id,
        private string $stage,
        private array $data,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function advanceTo(string $newStage): void
    {
        $this->stage = $newStage;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public static function create(Paths $paths): self
    {
        $id = bin2hex(random_bytes(16));
        $session = new self($id, self::STAGE_INIT, []);
        $session->save($paths);

        return $session;
    }

    public static function load(string $id, Paths $paths): ?self
    {
        // Строгая защита от Path Traversal в ID
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }

        $file = self::sessionFile($id, $paths);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['stage']) || !is_string($data['stage'])) {
            return null;
        }

        return new self($id, $data['stage'], $data['data'] ?? []);
    }

    public function save(Paths $paths): void
    {
        $file = self::sessionFile($this->id, $paths);
        $dir = dirname($file);

        if (!Fs::isDir($dir)) {
            Fs::mkdir($dir);
        }

        $payload = [
            'id' => $this->id,
            'stage' => $this->stage,
            'data' => $this->data,
            'updated_at' => time(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode wizard session JSON');
        }

        Fs::writeAtomic($file, $json . "\n");
    }

    public function delete(Paths $paths): void
    {
        $file = self::sessionFile($this->id, $paths);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function sessionFile(string $id, Paths $paths): string
    {
        return $paths->root() . '/var/wizard/' . $id . '.json';
    }
}
