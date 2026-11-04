#!/usr/bin/env php
<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
use Damoony\Cli\Commands\AdminPasswdCommand;
use Damoony\Cli\Commands\AlliasListCommand;
use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Commands\BlockEditCommand;
use Damoony\Cli\Commands\BlockListCommand;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Commands\BlockWriteCommand;
use Damoony\Cli\Commands\ContentGcCommand;
use Damoony\Cli\Commands\ContentListCommand;
use Damoony\Cli\Commands\ContentRemoveCommand;
use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Commands\DataproviderAddMethodCommand;
use Damoony\Cli\Commands\AiEditPageCommand;
use Damoony\Cli\Commands\DbCallCommand;
use Damoony\Cli\Commands\DbDescribeCommand;
use Damoony\Cli\Commands\DbMethodsCommand;
use Damoony\Cli\Commands\DbMigrateCommand;
use Damoony\Cli\Commands\DbMutateCommand;
use Damoony\Cli\Commands\DbSchemaCommand;
use Damoony\Cli\Commands\DomainListCommand;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Commands\DomainRemoveCommand;
use Damoony\Cli\Commands\FillerEditCommand;
use Damoony\Cli\Commands\FillerListCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Commands\FillerWriteCommand;
use Damoony\Cli\Commands\MigratePageRulesCommand;
use Damoony\Cli\Commands\PageListCommand;
use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageReadCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Commands\SchemeCopyCommand;
use Damoony\Cli\Commands\SchemeListCommand;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\SchemeRemoveCommand;
use Damoony\Cli\Commands\SchemeSaveCommand;
use Damoony\Cli\Commands\SkinEditCommand;
use Damoony\Cli\Commands\SkinListCommand;
use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Commands\SkinRemoveCommand;
use Damoony\Cli\Commands\StructureCheckCommand;
use Damoony\Cli\Commands\StructureRepairCommand;
use Damoony\Cli\Commands\TrashClearCommand;
use Damoony\Cli\Commands\UserNewCommand;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Renderer;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Files\Paths;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$paths = new Paths($root);

// БД (из engine.config)
$engineConfigFile = $root . '/config/engine.config';
$engineConfig = is_file($engineConfigFile) ? Config::fromFile($engineConfigFile) : Config::fromArray([]);
$dbConfig = $engineConfig->getByKey(['db'], []);

if (!is_array($dbConfig) || $dbConfig === []) {
    $dbConfig = ['driver' => 'sqlite', 'path' => $root . '/var/damoony.db'];
} elseif (($dbConfig['driver'] ?? '') === 'sqlite' && isset($dbConfig['path'])) {
    $path = (string) $dbConfig['path'];
    if (!str_starts_with($path, '/') && !preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
        $dbConfig['path'] = $root . '/' . ltrim($path, '/\\');
    }
}

$siteConfigFile = $root . '/config/site.config';
$siteConfig = is_file($siteConfigFile) ? Config::fromFile($siteConfigFile) : Config::fromArray([]);

$registry = new CommandRegistry();
$registry->add(new HelpCommand($registry));
$registry->add(new DomainListCommand($paths));
$registry->add(new DomainNewCommand($paths));
$registry->add(new DomainRemoveCommand($paths));
$registry->add(new AlliasListCommand($paths));
$registry->add(new AlliasNewCommand($paths));
$registry->add(new AlliasRemoveCommand($paths));
$registry->add(new BlockNewCommand($paths));
$registry->add(new BlockEditCommand($paths));
$registry->add(new BlockRemoveCommand($paths));
$registry->add(new FillerNewCommand($paths));
$registry->add(new FillerToggleCommand($paths));
$registry->add(new FillerEditCommand($paths));
$registry->add(new PageListCommand($paths));
$registry->add(new PageNewCommand($paths));
$registry->add(new PageToggleCommand($paths));
$registry->add(new PageWriteCommand($paths));
$registry->add(new PageReadCommand($paths));
$registry->add(new SchemeNewCommand($paths));
$registry->add(new SchemeCopyCommand($paths));
$registry->add(new SchemeRemoveCommand($paths));
$registry->add(new SchemeSaveCommand($paths));
$registry->add(new DbMigrateCommand($paths, $dbConfig));
$registry->add(new AdminPasswdCommand($paths));
$registry->add(new TrashClearCommand($paths));
$registry->add(new StructureRepairCommand($paths));
$registry->add(new StructureCheckCommand($paths, $siteConfig));
$registry->add(new MigratePageRulesCommand($paths));
$registry->add(new ContentStoreCommand($paths));
$registry->add(new ContentRemoveCommand($paths));
$registry->add(new ContentListCommand($paths));
$registry->add(new ContentGcCommand($paths));
$registry->add(new SchemeListCommand($paths));
$registry->add(new BlockListCommand($paths));
$registry->add(new FillerListCommand($paths));
$registry->add(new SkinNewCommand($paths));
$registry->add(new SkinListCommand($paths));
$registry->add(new SkinEditCommand($paths));
$registry->add(new SkinRemoveCommand($paths));
$registry->add(new BlockWriteCommand($paths));
$registry->add(new FillerWriteCommand($paths));

$db = new Connection($dbConfig);
$registry->add(new UserNewCommand($db));
$registry->add(new DbSchemaCommand($siteConfig, $db));
$registry->add(new DbMethodsCommand($paths));
$registry->add(new DbDescribeCommand($paths));
$registry->add(new DbCallCommand($paths, $siteConfig, $db));
$registry->add(new DbMutateCommand($paths, $siteConfig, $db));
$registry->add(new DataproviderAddMethodCommand($paths));
$registry->add(new AiEditPageCommand($paths));

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));
