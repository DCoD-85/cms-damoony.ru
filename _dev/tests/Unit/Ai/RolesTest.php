<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\Roles;
use PHPUnit\Framework\TestCase;

final class RolesTest extends TestCase
{
    public function testAllRolesValid(): void
    {
        foreach (Roles::all() as $role) {
            self::assertTrue(Roles::isValid($role), "role '{$role}' should be valid");
            self::assertNotEmpty(Roles::whitelist($role), "role '{$role}' whitelist empty");
        }
    }

    public function testStarIsValid(): void
    {
        self::assertTrue(Roles::isValid('*'));
        $wl = Roles::whitelist('*');
        self::assertNotEmpty($wl);
        self::assertContains('domain:new', $wl);
        self::assertContains('net:status', $wl);

        // Проверяем инвариант: роль '*' должна содержать все команды из всех ролей
        foreach (Roles::all() as $role) {
            foreach (Roles::whitelist($role) as $cmd) {
                self::assertContains($cmd, $wl, "Role '*' is missing '{$cmd}' from role '{$role}'");
            }
        }
    }

    public function testUnknownInvalid(): void
    {
        self::assertFalse(Roles::isValid('unknown_role'));
        self::assertSame([], Roles::whitelist('unknown_role'));
    }

    public function testReporterReadOnly(): void
    {
        $wl = Roles::whitelist(Roles::REPORTER);
        self::assertContains('structure:check', $wl);
        self::assertContains('page:read', $wl);
        foreach ($wl as $cmd) {
            self::assertStringNotContainsString(':new', $cmd);
            self::assertStringNotContainsString(':write', $cmd);
            self::assertStringNotContainsString(':edit', $cmd);
            self::assertStringNotContainsString(':remove', $cmd);
            self::assertStringNotContainsString(':save', $cmd);
        }
    }

    public function testArchitectCreatesEntities(): void
    {
        $wl = Roles::whitelist(Roles::ARCHITECT);
        self::assertContains('domain:new', $wl);
        self::assertContains('allias:new', $wl);
        self::assertContains('skin:new', $wl);
        self::assertContains('block:new', $wl);
        self::assertContains('scheme:new', $wl);
        self::assertContains('filler:new', $wl);
        self::assertNotContains('page:write', $wl);
    }

    public function testExecutorWritesPages(): void
    {
        $wl = Roles::whitelist(Roles::EXECUTOR);
        self::assertContains('page:write', $wl);
        self::assertContains('image:search', $wl);
        self::assertNotContains('domain:new', $wl);
        self::assertNotContains('skin:new', $wl);
    }

    public function testDesignerEdits(): void
    {
        $wl = Roles::whitelist(Roles::DESIGNER);
        self::assertContains('skin:edit', $wl);
        self::assertContains('scheme:save', $wl);
    }

    public function testInspectorReadOnly(): void
    {
        $wl = Roles::whitelist(Roles::INSPECTOR);
        self::assertContains('structure:check', $wl);
        foreach ($wl as $cmd) {
            self::assertStringNotContainsString(':new', $cmd);
            self::assertStringNotContainsString(':write', $cmd);
            self::assertStringNotContainsString(':edit', $cmd);
        }
    }

    public function testParserReadOnly(): void
    {
        $wl = Roles::whitelist(Roles::PARSER);
        foreach ($wl as $cmd) {
            self::assertMatchesRegularExpression('/:list$|:read$|:check$/', $cmd);
        }
    }

    public function testNoDuplicateCommandsAcrossRole(): void
    {
        foreach (Roles::all() as $role) {
            $wl = Roles::whitelist($role);
            self::assertSame($wl, array_values(array_unique($wl)), "role '{$role}' has duplicates");
        }
    }

    public function testWildcardIsSupersetOfAllRoles(): void
    {
        // Каждая команда каждой роли должна быть доступна в role='*'.
        // Проверяем через aicli.php: attempt block:new (есть у architect, но не было у *).
        $aicli = __DIR__ . '/../../../../bin/aicli.php';
        $payload = json_encode([
            'command' => 'block:new',
            'role' => '*',
            'args' => ['domain' => 'invalid..ru', 'name' => 'x'],
        ]);
        $proc = proc_open(
            'php ' . escapeshellarg($aicli),
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($proc);
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $decoded = json_decode((string) $out, true);
        self::assertIsArray($decoded);
        // Не должно быть "Command 'block:new' is not allowed for role '*'"
        self::assertNotSame(
            'Command \'block:new\' is not allowed for role \'*\'.',
            $decoded['error'] ?? '',
        );
        // Должна быть другая ошибка (валидация домена / конфига), не whitelist
        self::assertFalse($decoded['ok'] ?? false);
    }

    /**
     * After the Roles cleanup:
     * - block:edit / filler:edit removed (no handler in aicli.php)
     * - structure:check / structure:repair / scheme:new kept (handlers added)
     */
    public function testWildcardDoesNotAdvertiseEditCommandsWithoutHandlers(): void
    {
        $star = Roles::whitelist('*');
        $this->assertNotContains('block:edit', $star, 'block:edit has no aicli handler — remove or add handler');
        $this->assertNotContains('filler:edit', $star, 'filler:edit has no aicli handler — remove or add handler');
    }

    public function testWildcardKeepsDiagnosticAndSchemeCommands(): void
    {
        $star = Roles::whitelist('*');
        $this->assertContains('structure:check', $star);
        $this->assertContains('structure:repair', $star);
        $this->assertContains('scheme:new', $star);
    }

    public function testWizardCommandsAreAssignedToRoles(): void
    {
        $architect = Roles::whitelist(Roles::ARCHITECT);
        self::assertContains('wizard:start', $architect);
        self::assertContains('wizard:plan', $architect);
        self::assertNotContains('wizard:content', $architect); // Архитектор не пишет контент

        $executor = Roles::whitelist(Roles::EXECUTOR);
        self::assertContains('wizard:content', $executor);
        self::assertContains('wizard:finish', $executor);
        self::assertNotContains('wizard:plan', $executor); // Исполнитель не меняет план

        $designer = Roles::whitelist(Roles::DESIGNER);
        self::assertContains('wizard:skin', $designer);
    }

    public function testWildcardHasAllWizardCommands(): void
    {
        $star = Roles::whitelist('*');
        self::assertContains('wizard:start', $star);
        self::assertContains('wizard:domain', $star);
        self::assertContains('wizard:allias', $star);
        self::assertContains('wizard:plan', $star);
        self::assertContains('wizard:content', $star);
        self::assertContains('wizard:skin', $star);
        self::assertContains('wizard:finish', $star);
        self::assertContains('wizard:commit', $star);
        self::assertContains('wizard:abort', $star);
    }

    public function testCorrectorRoleKeepsStructureRepair(): void
    {
        $corrector = Roles::whitelist(Roles::CORRECTOR);
        $this->assertContains('structure:repair', $corrector);
        $this->assertContains('structure:check', $corrector);
    }
}
