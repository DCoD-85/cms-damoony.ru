<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class Result
{
    public bool $ok = true;

    /** @var list<array{message: string, ok: bool, detail: string}> */
    public array $steps = [];

    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $errors = [];

    public function step(string $message, bool $ok = true, string $detail = ''): void
    {
        $this->steps[] = ['message' => $message, 'ok' => $ok, 'detail' => $detail];
        if (!$ok) {
            $this->ok = false;
        }
    }

    public function file(string $path): void
    {
        $this->created[] = $path;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
        $this->ok = false;
    }
}