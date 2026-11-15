<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class ArgvParser
{
    /**
     * @param list<string> $argv
     *
     * @return array{command: string, args: array<string, string>, flags: list<string>, positional: list<string>}
     */
    public function parse(array $argv): array
    {
        $command = '';
        $args = [];
        $flags = [];
        $positional = [];

        foreach ($argv as $i => $token) {
            if ($i === 0) {
                continue;
            }
            if ($command === '' && !str_starts_with($token, '-')) {
                $command = $token;
                continue;
            }
            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                if (str_contains($body, '=')) {
                    [$k, $v] = explode('=', $body, 2);
                    $args[$k] = $v;
                } else {
                    $flags[] = $body;
                    $args[$body] = '1';
                }
                continue;
            }
            if (str_starts_with($token, '-') && $token !== '-') {
                $flags[] = ltrim($token, '-');
                continue;
            }
            $positional[] = $token;
        }

        return [
            'command' => $command,
            'args' => $args,
            'flags' => $flags,
            'positional' => $positional,
        ];
    }
}
