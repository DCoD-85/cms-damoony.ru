<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\WizardManager;
use Damoony\Ai\WizardSession;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class WizardManagerTest extends TestCase
{
    private string $root;
    private Paths $paths;
    private array $cliCalls = [];
    private bool $cliShouldFail = false;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_wizard_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/var', 0o777, true);
        $this->paths = new Paths($this->root);
        $this->cliCalls = [];
        $this->cliShouldFail = false;
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function createManager(): WizardManager
    {
        return new WizardManager(
            $this->paths,
            function (string $cmd): array {
                $this->cliCalls[] = $cmd;
                if ($this->cliShouldFail) {
                    return ['ok' => false, 'output' => 'Mocked failure', 'error' => 'Fail', 'hint' => ''];
                }
                return ['ok' => true, 'output' => 'OK', 'error' => '', 'hint' => ''];
            },
            function (string $cmd): array {
                $this->cliCalls[] = $cmd;
                if (str_contains($cmd, 'domain:list')) {
                    return ['ok' => true, 'data' => ['rows' => []]];
                }
                return ['ok' => true, 'data' => []];
            },
        );
    }

    public function testFullWizardLifecycle(): void
    {
        $session = WizardSession::create($this->paths);
        $manager = $this->createManager();

        // 1. Domain
        $r = $manager->handle($session, 'domain', ['domain' => 'test.ru']);
        self::assertTrue($r->ok, implode('; ', $r->errors));
        self::assertSame(WizardSession::STAGE_DOMAIN, $session->stage());

        // 2. Allias
        $r = $manager->handle($session, 'allias', ['allias' => 'www']);
        self::assertTrue($r->ok);
        self::assertSame(WizardSession::STAGE_ALLIAS, $session->stage());

        // 3. Plan
        $plan = [
            ['name' => '__root__', 'title' => 'Home', 'parent' => null],
            ['name' => 'about', 'title' => 'About', 'parent' => null],
        ];
        $r = $manager->handle($session, 'plan', ['pages' => $plan]);
        self::assertTrue($r->ok, implode('; ', $r->errors));
        self::assertSame(WizardSession::STAGE_PLAN, $session->stage());

        // 4. Content
        $blocks = [['type' => 'h1', 'content' => 'Hello']];
        $r = $manager->handle($session, 'content', ['page' => '__root__', 'blocks' => $blocks]);
        self::assertTrue($r->ok);
        $r = $manager->handle($session, 'content', ['page' => 'about', 'blocks' => $blocks]);
        self::assertTrue($r->ok);
        self::assertSame(WizardSession::STAGE_CONTENT, $session->stage());

        // 5. Skin (optional)
        $r = $manager->handle($session, 'skin', ['file' => 'css/main.css', 'content' => 'body{}']);
        self::assertTrue($r->ok);
        self::assertSame(WizardSession::STAGE_SKIN, $session->stage());

        // 6. Finish
        $r = $manager->handle($session, 'finish', []);
        self::assertTrue($r->ok, implode('; ', $r->errors));
        self::assertSame(WizardSession::STAGE_READY, $session->stage());

        // 7. Commit
        $r = $manager->handle($session, 'commit', []);
        self::assertTrue($r->ok, implode('; ', $r->errors));
        self::assertSame(WizardSession::STAGE_COMMITTED, $session->stage());

        // Verify CLI calls (cross-platform check to bypass escapeshellarg quoting differences)
        self::assertTrue($this->hasCliCall('domain:new', ['test.ru']), 'domain:new not called');
        self::assertTrue($this->hasCliCall('allias:new', ['test.ru', 'www']), 'allias:new not called');
        self::assertTrue($this->hasCliCall('page:new', ['test.ru', 'about']), 'page:new not called');
    }

    public function testRejectsWrongStage(): void
    {
        $session = WizardSession::create($this->paths);
        $manager = $this->createManager();

        // Try allias before domain
        $r = $manager->handle($session, 'allias', ['allias' => 'www']);
        self::assertFalse($r->ok);
        self::assertStringContainsString('Expected: domain', $r->errors[0]);
    }

    public function testRejectsInvalidPlan(): void
    {
        $session = WizardSession::create($this->paths);
        $manager = $this->createManager();

        $manager->handle($session, 'domain', ['domain' => 'test.ru']);
        $manager->handle($session, 'allias', ['allias' => 'www']);

        // Missing __root__
        $r = $manager->handle($session, 'plan', ['pages' => [['name' => 'about', 'title' => 'A', 'parent' => null]]]);
        self::assertFalse($r->ok);
        self::assertStringContainsString('must include exactly one __root__', $r->errors[0]);

        // Orphan parent
        $plan = [
            ['name' => '__root__', 'title' => 'Home', 'parent' => null],
            ['name' => 'about', 'title' => 'About', 'parent' => 'ghost'],
        ];
        $r = $manager->handle($session, 'plan', ['pages' => $plan]);
        self::assertFalse($r->ok);
        self::assertStringContainsString('does not exist in the plan', $r->errors[0]);
    }

    public function testRollbackOnCommitFailure(): void
    {
        $session = WizardSession::create($this->paths);

        // Custom manager that fails only on 'skin:new'
        $manager = new WizardManager(
            $this->paths,
            function (string $cmd): array {
                $this->cliCalls[] = $cmd;
                if (str_contains($cmd, 'skin:new')) {
                    return ['ok' => false, 'output' => 'Mocked skin failure', 'error' => 'Fail', 'hint' => ''];
                }
                return ['ok' => true, 'output' => 'OK', 'error' => '', 'hint' => ''];
            },
            function (string $cmd): array {
                $this->cliCalls[] = $cmd;
                return ['ok' => true, 'data' => ['rows' => []]];
            },
        );

        $manager->handle($session, 'domain', ['domain' => 'test.ru']);
        $manager->handle($session, 'allias', ['allias' => 'www']);
        $manager->handle($session, 'plan', ['pages' => [
            ['name' => '__root__', 'title' => 'Home', 'parent' => null],
        ]]);
        $manager->handle($session, 'content', ['page' => '__root__', 'blocks' => []]);
        $manager->handle($session, 'finish', []);

        $r = $manager->handle($session, 'commit', []);

        self::assertFalse($r->ok);
        self::assertStringContainsString('skin:new failed', $r->errors[0]);

        // Rollback should call remove commands (domain + allias, because they were created before the failure)
        self::assertTrue($this->hasCliCall('domain:remove', ['test.ru', '--force']), 'domain:remove not called');
        self::assertTrue($this->hasCliCall('allias:remove', ['test.ru', 'www', '--force']), 'allias:remove not called');
    }

    /** @param list<string> $fragments */
    private function hasCliCall(string $command, array $fragments): bool
    {
        foreach ($this->cliCalls as $call) {
            if (str_contains($call, $command)) {
                $allFound = true;
                foreach ($fragments as $fragment) {
                    if (!str_contains($call, $fragment)) {
                        $allFound = false;
                        break;
                    }
                }
                if ($allFound) {
                    return true;
                }
            }
        }
        return false;
    }
}
