<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai\Orchestrator;

use Damoony\Ai\Orchestrator\Agent\ArchitectAgentInterface;
use Damoony\Ai\Orchestrator\Agent\CoderAgentInterface;
use Damoony\Ai\Orchestrator\Agent\InterviewerAgentInterface;
use Damoony\Ai\Orchestrator\ContextManager;
use Damoony\Ai\Orchestrator\Orchestrator;
use Damoony\Ai\Orchestrator\ToolsCollectorInterface;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class OrchestratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/orch_run_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/app', 0o775, true);
        mkdir($this->root . '/app/.sess_a1b2c3d4.damoony.ru', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testRunWritesToolsContext(): void
    {
        $fake = new class () implements ToolsCollectorInterface {
            public function collect(string $domain, string $alias): array
            {
                return [
                    'structure_ok' => true,
                    'structure_errors' => [],
                    'db_tables' => ['users', 'news'],
                    'errors' => [],
                ];
            }
        };

        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $fakeInterviewer = new class () implements InterviewerAgentInterface {
            public function execute(array $context): array
            {
                return ['action' => 'build', 'message' => 'test', 'brief' => 'Test prompt'];
            }
        };
        $fakeArchitect = new class () implements ArchitectAgentInterface {
            public function execute(array $context): array
            {
                return ['routing_tree' => [], 'slot_assignment' => []];
            }
        };
        $fakeCoder = new class () implements CoderAgentInterface {
            public function execute(array $context): array
            {
                return ['blocks' => []];
            }
            public function executeForPage(array $context, array $pagePlan): array
            {
                return ['blocks' => []];
            }
        };
        $fakeAicli = new \Damoony\Cli\Ai\AicliClient($this->root);
        $orchestrator = new Orchestrator($cm, $fake, $fakeInterviewer, $fakeArchitect, $fakeCoder, $fakeAicli);

        $ctx = $orchestrator->run('a1b2c3d4', 'damoony.ru', 'www', 'Test prompt', 'center');

        self::assertSame('coder_done', $ctx['meta']['status']);
        self::assertSame(['users', 'news'], $ctx['tools_context']['db_tables']);
        self::assertTrue($ctx['tools_context']['structure_ok']);
        self::assertSame('Test prompt', $ctx['input']['prompt']);
        self::assertSame('center', $ctx['meta']['target_block']);
    }

    public function testRunMarksFailedOnToolErrors(): void
    {
        $fake = new class () implements ToolsCollectorInterface {
            public function collect(string $domain, string $alias): array
            {
                return [
                    'structure_ok' => true,
                    'structure_errors' => [],
                    'db_tables' => [],
                    'errors' => ['db:schema failed: connection refused'],
                ];
            }
        };

        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $fakeInterviewer = new class () implements InterviewerAgentInterface {
            public function execute(array $context): array
            {
                return ['action' => 'build', 'message' => 'test', 'brief' => 'Test prompt'];
            }
        };
        $fakeArchitect = new class () implements ArchitectAgentInterface {
            public function execute(array $context): array
            {
                return ['routing_tree' => [], 'slot_assignment' => []];
            }
        };
        $fakeCoder = new class () implements CoderAgentInterface {
            public function execute(array $context): array
            {
                return ['blocks' => []];
            }
            public function executeForPage(array $context, array $pagePlan): array
            {
                return ['blocks' => []];
            }
        };
        $fakeAicli = new \Damoony\Cli\Ai\AicliClient($this->root);
        $orchestrator = new Orchestrator($cm, $fake, $fakeInterviewer, $fakeArchitect, $fakeCoder, $fakeAicli);

        $ctx = $orchestrator->run('a1b2c3d4', 'damoony.ru', 'www', 'Test', 'all');

        self::assertSame('tools_failed', $ctx['meta']['status']);
        self::assertSame(['db:schema failed: connection refused'], $ctx['tools_context']['errors']);
    }

    public function testRunUsesDomainAndAliasInCollector(): void
    {
        $fake = new class () implements ToolsCollectorInterface {
            public ?string $seenDomain = null;
            public ?string $seenAlias = null;

            public function collect(string $domain, string $alias): array
            {
                $this->seenDomain = $domain;
                $this->seenAlias = $alias;
                return [
                    'structure_ok' => true,
                    'structure_errors' => [],
                    'db_tables' => [],
                    'errors' => [],
                ];
            }
        };

        $paths = new Paths($this->root);
        $cm = new ContextManager($paths, 'a1b2c3d4', 'damoony.ru');
        $fakeInterviewer = new class () implements InterviewerAgentInterface {
            public function execute(array $context): array
            {
                return ['action' => 'build', 'message' => 'test', 'brief' => 'Test prompt'];
            }
        };
        $fakeArchitect = new class () implements ArchitectAgentInterface {
            public function execute(array $context): array
            {
                return ['routing_tree' => [], 'slot_assignment' => []];
            }
        };
        $fakeCoder = new class () implements CoderAgentInterface {
            public function execute(array $context): array
            {
                return ['blocks' => []];
            }
            public function executeForPage(array $context, array $pagePlan): array
            {
                return ['blocks' => []];
            }
        };
        $fakeAicli = new \Damoony\Cli\Ai\AicliClient($this->root);
        $orchestrator = new Orchestrator($cm, $fake, $fakeInterviewer, $fakeArchitect, $fakeCoder, $fakeAicli);

        $orchestrator->run('a1b2c3d4', 'damoony.ru', 'www', 'Test');

        self::assertSame('damoony.ru', $fake->seenDomain);
        self::assertSame('www', $fake->seenAlias);
    }

    private function rmrf(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $d . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($d);
    }
}
