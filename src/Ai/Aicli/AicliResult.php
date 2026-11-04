<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

/**
 * Результат выполнения AI-команды.
 *
 * Readonly: handler не может «дописать» данные после возврата.
 * role/next добавляются на уровне dispatch (bin/aicli.php), не в handler.
 */
final readonly class AicliResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $ok,
        public array $data = [],
        public string $error = '',
        public string $hint = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(array $data = []): self
    {
        return new self(ok: true, data: $data);
    }

    public static function fail(string $error, string $hint = ''): self
    {
        return new self(ok: false, error: $error, hint: $hint);
    }

    /**
     * @param array{ok: bool, output: string, error: string, hint: string} $cli
     */
    public static function fromCli(array $cli): self
    {
        return new self(
            ok: (bool) $cli['ok'],
            data: ['output' => (string) $cli['output']],
            error: (string) $cli['error'],
            hint: (string) $cli['hint'],
        );
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>, error: string, hint: string}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'data' => $this->data,
            'error' => $this->error,
            'hint' => $this->hint,
        ];
    }
}
