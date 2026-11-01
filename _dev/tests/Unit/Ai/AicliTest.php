<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;

/**
 * Integration-тесты bin/aicli.php через proc_open (STDIN → STDOUT).
 * Только offline-сценарии: whitelist, path traversal, validation, JSON, fail-safe.
 * Сетевые команды (image:search, fetch) — не тестируются здесь (медленно, ненадёжно).
 */
final class AicliTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);

        // Тестовый agent.config (не трогаем реальный)
        $configDir = $this->projectRoot . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0o775, true);
        }
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{exit: int, output: array<string, mixed>|null, raw: string}
     */
    private function invoke(array $request): array
    {
        $json = json_encode($request, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            self::fail('Cannot encode request');
        }

        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg($this->projectRoot . '/bin/aicli.php');

        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open("{$php} {$script}", $desc, $pipes, $this->projectRoot);
        if (!is_resource($proc)) {
            self::fail('proc_open failed');
        }

        fwrite($pipes[0], $json);
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exit = proc_close($proc);

        $decoded = json_decode(trim($stdout), true);

        return [
            'exit' => $exit,
            'output' => is_array($decoded) ? $decoded : null,
            'raw' => $stdout . ($stderr !== '' ? "\n[stderr] " . $stderr : ''),
        ];
    }

    public function testInvalidJson(): void
    {
        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg($this->projectRoot . '/bin/aicli.php');

        $proc = proc_open("echo not-json | {$php} {$script}", [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->projectRoot);
        self::assertIsResource($proc);

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        $data = json_decode(trim($stdout), true);
        self::assertIsArray($data);
        self::assertFalse($data['ok']);
        self::assertSame('Invalid JSON request', $data['error']);
        self::assertSame(1, $exit);
    }

    public function testUnknownCommand(): void
    {
        $r = $this->invoke(['command' => 'evil:cmd', 'args' => []]);
        self::assertSame(1, $r['exit']);
        self::assertIsArray($r['output']);
        self::assertFalse($r['output']['ok']);
        self::assertStringContainsString("Command 'evil:cmd' is not allowed", $r['output']['error']);
    }

    public function testDangerousCommandRejected(): void
    {
        // user:new, db:migrate, scheme:* — в whitelist AI отсутствуют
        foreach (['user:new', 'db:migrate', 'admin:passwd', 'scheme:remove', 'scheme:copy', 'trash:clear'] as $cmd) {
            $r = $this->invoke(['command' => $cmd, 'args' => []]);
            self::assertSame(1, $r['exit'], "Command '{$cmd}' must be rejected");
            self::assertIsArray($r['output']);
            self::assertFalse($r['output']['ok']);
            self::assertStringContainsString('is not allowed', $r['output']['error']);
        }
    }

    public function testReadTestFilePathTraversal(): void
    {
        $r = $this->invoke(['command' => 'read_test_file', 'args' => ['filename' => '../../etc/passwd']]);
        self::assertSame(1, $r['exit']);
        self::assertIsArray($r['output']);
        self::assertFalse($r['output']['ok']);
        self::assertStringContainsString('Invalid filename', $r['output']['error']);
    }

    public function testImageDownloadDomainNotInAgentConfig(): void
    {
        // evil.com точно не в agent.config (allowed_domains)
        $r = $this->invoke([
            'command' => 'image:download',
            'args' => [
                'url' => 'https://upload.wikimedia.org/x.jpg',
                'domain' => 'evil.com',
                'allias' => 'www',
                'category' => 'test',
            ],
        ]);
        self::assertSame(1, $r['exit']);
        self::assertIsArray($r['output']);
        self::assertFalse($r['output']['ok']);
        self::assertStringContainsString('Security violation', $r['output']['error']);
    }

    public function testPageWritePathTraversalInDomain(): void
    {
        $r = $this->invoke([
            'command' => 'page:write',
            'args' => [
                'domain' => 'a..ru',
                'allias' => 'www',
                'name' => 'about',
                'blocks' => [],
            ],
        ]);
        self::assertSame(1, $r['exit']);
        self::assertIsArray($r['output']);
        self::assertFalse($r['output']['ok']);
        // Домен не в allowed_domains → security violation
        self::assertStringContainsString('Security violation', $r['output']['error']);
    }

    public function testNetStatusIsWhitelisted(): void
    {
        // net:status либо ok (если есть config), либо fail-safe (если конфига нет) —
        // но НЕ "command not allowed"
        $r = $this->invoke(['command' => 'net:status', 'args' => []]);
        self::assertIsArray($r['output']);
        $outputStr = (string) json_encode($r['output']);
        self::assertStringNotContainsString('is not allowed', $outputStr);
        // Либо ok, либо AINET init failed
        self::assertTrue(
            $r['output']['ok'] === true || str_contains((string) ($r['output']['error'] ?? ''), 'AINET init failed'),
            'net:status must be ok or fail-safe: ' . $outputStr,
        );
    }

    public function testNetCommandsInWhitelistHint(): void
    {
        // При reject — hint перечисляет все разрешённые, включая net:*
        $r = $this->invoke(['command' => 'unknown', 'args' => []]);
        self::assertIsArray($r['output']);
        $hint = (string) ($r['output']['hint'] ?? '');
        self::assertStringContainsString('net:status', $hint);
        self::assertStringContainsString('image:search', $hint);
        self::assertStringContainsString('image:download', $hint);
        self::assertStringContainsString('fetch', $hint);
    }
}
