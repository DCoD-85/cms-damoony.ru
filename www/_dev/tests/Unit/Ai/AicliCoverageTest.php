<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai;

use Damoony\Ai\Roles;
use Damoony\Cli\Ai\AicliClient;
use Damoony\Cli\Ai\Handler\AgentChatHandler;
use Damoony\Cli\Ai\Handler\AiStartHandler;
use Damoony\Cli\Ai\Handler\AlliasListHandler;
use Damoony\Cli\Ai\Handler\AlliasNewHandler;
use Damoony\Cli\Ai\Handler\BlockListHandler;
use Damoony\Cli\Ai\Handler\BlockNewHandler;
use Damoony\Cli\Ai\Handler\BlockWriteHandler;
use Damoony\Cli\Ai\Handler\DataproviderAddMethodHandler;
use Damoony\Cli\Ai\Handler\DbCallHandler;
use Damoony\Cli\Ai\Handler\DbDescribeHandler;
use Damoony\Cli\Ai\Handler\DbMethodsHandler;
use Damoony\Cli\Ai\Handler\DbMutateHandler;
use Damoony\Cli\Ai\Handler\DbSchemaHandler;
use Damoony\Cli\Ai\Handler\DomainListHandler;
use Damoony\Cli\Ai\Handler\DomainNewHandler;
use Damoony\Cli\Ai\Handler\DoneHandler;
use Damoony\Cli\Ai\Handler\EngineStatusHandler;
use Damoony\Cli\Ai\Handler\FillerListHandler;
use Damoony\Cli\Ai\Handler\FillerNewHandler;
use Damoony\Cli\Ai\Handler\FillerWriteHandler;
use Damoony\Cli\Ai\Handler\NetHandler;
use Damoony\Cli\Ai\Handler\PageListHandler;
use Damoony\Cli\Ai\Handler\PageNewHandler;
use Damoony\Cli\Ai\Handler\PageReadHandler;
use Damoony\Cli\Ai\Handler\PageWriteHandler;
use Damoony\Cli\Ai\Handler\SchemeListHandler;
use Damoony\Cli\Ai\Handler\SchemeNewHandler;
use Damoony\Cli\Ai\Handler\SchemeSaveHandler;
use Damoony\Cli\Ai\Handler\SkinEditHandler;
use Damoony\Cli\Ai\Handler\SkinListHandler;
use Damoony\Cli\Ai\Handler\SkinNewHandler;
use Damoony\Cli\Ai\Handler\StructureCheckHandler;
use Damoony\Cli\Ai\Handler\StructureRepairHandler;
use Damoony\Cli\Ai\Handler\WizardHandler;
use Damoony\Cli\Ai\HandlerRegistry;
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
