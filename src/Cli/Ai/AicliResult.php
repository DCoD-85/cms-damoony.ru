<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai;

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
     * Из результата AicliClient::runJson() (формат {ok, data, error, hint}).
     *
     * @param array<string, mixed> $json
     */
    public static function fromJson(array $json): self
    {
        $data = $json['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        // CLI Result::error() пишет в 'errors' (array); также поддерживаем 'error' (scalar)
        $error = (string) ($json['error'] ?? '');
        if ($error === '' && isset($json['errors']) && is_array($json['errors'])) {
            $parts = [];
            foreach ($json['errors'] as $e) {
                $parts[] = is_string($e) ? $e : (string) json_encode($e);
            }
            $error = implode('; ', $parts);
        }

        return new self(
            ok: (bool) ($json['ok'] ?? false),
            data: $data,
            error: $error,
            hint: (string) ($json['hint'] ?? ''),
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
