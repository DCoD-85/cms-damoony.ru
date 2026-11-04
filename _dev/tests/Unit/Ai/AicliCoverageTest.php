<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;
use Damoony\Ai\Aicli\Handler\AiStartHandler;
use Damoony\Ai\Aicli\Handler\AlliasListHandler;
use Damoony\Ai\Aicli\Handler\AlliasNewHandler;
use Damoony\Ai\Aicli\Handler\BlockListHandler;
use Damoony\Ai\Aicli\Handler\BlockNewHandler;
use Damoony\Ai\Aicli\Handler\BlockWriteHandler;
use Damoony\Ai\Aicli\Handler\DataproviderAddMethodHandler;
use Damoony\Ai\Aicli\Handler\DbCallHandler;
use Damoony\Ai\Aicli\Handler\DbDescribeHandler;
use Damoony\Ai\Aicli\Handler\DbMethodsHandler;
use Damoony\Ai\Aicli\Handler\DbMutateHandler;
use Damoony\Ai\Aicli\Handler\DbSchemaHandler;
use Damoony\Ai\Aicli\Handler\DomainListHandler;
use Damoony\Ai\Aicli\Handler\DomainNewHandler;
use Damoony\Ai\Aicli\Handler\DoneHandler;
use Damoony\Ai\Aicli\Handler\EngineStatusHandler;
use Damoony\Ai\Aicli\Handler\FillerListHandler;
use Damoony\Ai\Aicli\Handler\FillerNewHandler;
use Damoony\Ai\Aicli\Handler\FillerWriteHandler;
use Damoony\Ai\Aicli\Handler\NetHandler;
use Damoony\Ai\Aicli\Handler\PageListHandler;
use Damoony\Ai\Aicli\Handler\PageNewHandler;
use Damoony\Ai\Aicli\Handler\PageReadHandler;
use Damoony\Ai\Aicli\Handler\PageWriteHandler;
use Damoony\Ai\Aicli\Handler\SchemeListHandler;
use Damoony\Ai\Aicli\Handler\SchemeNewHandler;
use Damoony\Ai\Aicli\Handler\SchemeSaveHandler;
use Damoony\Ai\Aicli\Handler\SkinEditHandler;
use Damoony\Ai\Aicli\Handler\SkinListHandler;
use Damoony\Ai\Aicli\Handler\SkinNewHandler;
use Damoony\Ai\Aicli\Handler\StructureCheckHandler;
use Damoony\Ai\Aicli\Handler\StructureRepairHandler;
use Damoony\Ai\Aicli\Handler\WizardHandler;
use Damoony\Ai\Aicli\HandlerRegistry;
use Damoony\Ai\Roles;
use PHPUnit\Framework\TestCase;

final class AicliCoverageTest extends TestCase
{
    private static ?HandlerRegistry $registry = null;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 4);
        $client = new AicliClient($root);

        $registry = new HandlerRegistry();
        $registry->add(new WizardHandler($client, $root));
        $registry->add(new NetHandler($client, $root));
        $registry->add(new AgentChatHandler($client, $root));
        $registry->add(new AiStartHandler($client, $root));
        $registry->add(new DoneHandler($client, $root));
        $registry->add(new EngineStatusHandler($client, $root));
        $registry->add(new StructureCheckHandler($client, $root));
        $registry->add(new StructureRepairHandler($client, $root));
        $registry->add(new DbSchemaHandler($client, $root));
        $registry->add(new DbMethodsHandler($client, $root));
        $registry->add(new DbDescribeHandler($client, $root));
        $registry->add(new DbCallHandler($client, $root));
        $registry->add(new DbMutateHandler($client, $root));
        $registry->add(new DataproviderAddMethodHandler($client, $root));
        $registry->add(new DomainListHandler($client, $root));
        $registry->add(new DomainNewHandler($client, $root));
        $registry->add(new AlliasListHandler($client, $root));
        $registry->add(new AlliasNewHandler($client, $root));
        $registry->add(new PageListHandler($client, $root));
        $registry->add(new PageNewHandler($client, $root));
        $registry->add(new PageReadHandler($client, $root));
        $registry->add(new PageWriteHandler($client, $root));
        $registry->add(new SchemeListHandler($client, $root));
        $registry->add(new SchemeNewHandler($client, $root));
        $registry->add(new SchemeSaveHandler($client, $root));
        $registry->add(new BlockListHandler($client, $root));
        $registry->add(new BlockNewHandler($client, $root));
        $registry->add(new BlockWriteHandler($client, $root));
        $registry->add(new FillerListHandler($client, $root));
        $registry->add(new FillerNewHandler($client, $root));
        $registry->add(new FillerWriteHandler($client, $root));
        $registry->add(new SkinListHandler($client, $root));
        $registry->add(new SkinNewHandler($client, $root));
        $registry->add(new SkinEditHandler($client, $root));

        self::$registry = $registry;
    }

    public function testEveryWhitelistedCommandHasHandler(): void
    {
        $missing = [];
        foreach (Roles::whitelist('*') as $cmd) {
            if (!self::$registry->has($cmd)) {
                $missing[] = $cmd;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Commands advertised in Roles::whitelist('*') but not registered in any handler:\n  - "
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

    public function testWizardPrefixIsRegistered(): void
    {
        $this->assertTrue(self::$registry->has('wizard:start'));
        $this->assertTrue(self::$registry->has('wizard:domain'));
        $this->assertTrue(self::$registry->has('wizard:commit'));
        $this->assertTrue(self::$registry->has('wizard:arbitrary'));
    }
}
