<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\Roles;
use PHPUnit\Framework\TestCase;

final class AicliCoverageTest extends TestCase
{
    private string $aicliPath;
    private string $source = '';

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 4);
        $this->aicliPath = $root . '/bin/aicli.php';
        $this->assertFileExists(
            $this->aicliPath,
            'bin/aicli.php missing — aicli coverage tests require this file'
        );
        $src = file_get_contents($this->aicliPath);
        $this->assertNotFalse($src);
        $this->source = $src;
    }

    public function testEveryWhitelistedCommandHasHandler(): void
    {
        $missing = [];
        foreach (Roles::whitelist('*') as $cmd) {
            // wizard:* commands are handled by a single str_starts_with block in aicli.php
            if (str_starts_with($cmd, 'wizard:')) {
                continue;
            }
            if (!$this->hasHandler($cmd)) {
                $missing[] = $cmd;
            }
        }
        $this->assertSame(
            [],
            $missing,
            "Commands advertised in Roles::whitelist('*') but not handled in bin/aicli.php:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    public function testWildcardIsSupersetOfAllRoles(): void
    {
        $star = Roles::whitelist('*');
        $missing = [];
        foreach (Roles::all() as $role) {
            foreach (Roles::whitelist($role) as $cmd) {
                if (!in_array($cmd, $star, true)) {
                    $missing[] = $role . ':' . $cmd;
                }
            }
        }
        $this->assertSame(
            [],
            $missing,
            'Commands in role MAP missing from wildcard: ' . implode(', ', $missing),
        );
    }

    private function hasHandler(string $cmd): bool
    {
        $pattern = '/\$command\s*===\s*' . preg_quote("'" . $cmd . "'", '/') . '\s*\)/';
        return (bool) preg_match($pattern, $this->source);
    }
}
