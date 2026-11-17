<?php

declare(strict_types=1);

namespace Damoony\Cli\Next;

use Damoony\Cli\CommandRegistry;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Identity\IdentityStore;
use Damoony\Cli\Next\Commands\AdminPasswdCommand;
use Damoony\Cli\Next\Commands\AlliasListCommand;
use Damoony\Cli\Next\Commands\AlliasNewCommand;
use Damoony\Cli\Next\Commands\AlliasRemoveCommand;
use Damoony\Cli\Next\Commands\ApiReflectCommand;
use Damoony\Cli\Next\Commands\BackupCreateCommand;
use Damoony\Cli\Next\Commands\BackupRestoreCommand;
use Damoony\Cli\Next\Commands\BlockEditCommand;
use Damoony\Cli\Next\Commands\BlockListCommand;
use Damoony\Cli\Next\Commands\BlockNewCommand;
use Damoony\Cli\Next\Commands\BlockRemoveCommand;
use Damoony\Cli\Next\Commands\BlockWriteCommand;
use Damoony\Cli\Next\Commands\ContentGcCommand;
use Damoony\Cli\Next\Commands\ContentListCommand;
use Damoony\Cli\Next\Commands\ContentRemoveCommand;
use Damoony\Cli\Next\Commands\ContentStoreCommand;
use Damoony\Cli\Next\Commands\DataproviderAddMethodCommand;
use Damoony\Cli\Next\Commands\DbCallCommand;
use Damoony\Cli\Next\Commands\DbDescribeCommand;
use Damoony\Cli\Next\Commands\DbMethodsCommand;
use Damoony\Cli\Next\Commands\DbMigrateCommand;
use Damoony\Cli\Next\Commands\DbMutateCommand;
use Damoony\Cli\Next\Commands\DbSchemaCommand;
use Damoony\Cli\Next\Commands\DomainBindCommand;
use Damoony\Cli\Next\Commands\DomainListCommand;
use Damoony\Cli\Next\Commands\DomainNewCommand;
use Damoony\Cli\Next\Commands\DomainRemoveCommand;
use Damoony\Cli\Next\Commands\DomainUnbindCommand;
use Damoony\Cli\Next\Commands\FillerEditCommand;
use Damoony\Cli\Next\Commands\FillerListCommand;
use Damoony\Cli\Next\Commands\FillerNewCommand;
use Damoony\Cli\Next\Commands\FillerToggleCommand;
use Damoony\Cli\Next\Commands\FillerWriteCommand;
use Damoony\Cli\Next\Commands\InitCommand;
use Damoony\Cli\Next\Commands\LangAddCommand;
use Damoony\Cli\Next\Commands\LangListCommand;
use Damoony\Cli\Next\Commands\LangRemoveCommand;
use Damoony\Cli\Next\Commands\LangRepairCommand;
use Damoony\Cli\Next\Commands\LangSyncCommand;
use Damoony\Cli\Next\Commands\MigratePageRulesCommand;
use Damoony\Cli\Next\Commands\MigrateSiteConfigCommand;
use Damoony\Cli\Next\Commands\NewCommand;
use Damoony\Cli\Next\Commands\PageListCommand;
use Damoony\Cli\Next\Commands\PageNewCommand;
use Damoony\Cli\Next\Commands\PageReadCommand;
use Damoony\Cli\Next\Commands\PageToggleCommand;
use Damoony\Cli\Next\Commands\PageWriteCommand;
use Damoony\Cli\Next\Commands\SchemeCopyCommand;
use Damoony\Cli\Next\Commands\SchemeListCommand;
use Damoony\Cli\Next\Commands\SchemeNewCommand;
use Damoony\Cli\Next\Commands\SchemeRemoveCommand;
use Damoony\Cli\Next\Commands\SchemeSaveCommand;
use Damoony\Cli\Next\Commands\SessionApplyCommand;
use Damoony\Cli\Next\Commands\SessionDiscardCommand;
use Damoony\Cli\Next\Commands\SessionListCommand;
use Damoony\Cli\Next\Commands\SessionStartCommand;
use Damoony\Cli\Next\Commands\SkinEditCommand;
use Damoony\Cli\Next\Commands\SkinListCommand;
use Damoony\Cli\Next\Commands\SkinNewCommand;
use Damoony\Cli\Next\Commands\SkinRemoveCommand;
use Damoony\Cli\Next\Commands\SystemCleanCommand;
use Damoony\Cli\Next\Commands\SystemScanCommand;
use Damoony\Cli\Next\Commands\TrashClearCommand;
use Damoony\Cli\Next\Commands\UserNewCommand;
use Damoony\Cli\Next\Scaffold\Scaffolder;
use Damoony\Cli\Next\Support\BlockFileEditor;
use Damoony\Cli\Next\Support\ContentLayout;
use Damoony\Cli\Next\Support\ContentRefs;
use Damoony\Cli\Next\Support\ProviderInvoker;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Cli\Next\Support\SessionGuard;
use Damoony\Cli\Next\Support\SessionWorkspace;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Next\Support\TrashScanner;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Data\Db\Connection;
use RuntimeException;

/**
 * Реестр нового CLI-стека (схема domains_app).
 *
 * Читает config/site.config. Старый формат (domains_real/domains_mirrors)
 * больше не поддерживается — файл называется site.config.old.
 */
final class Registry
{
    public static function configPath(string $root): string
    {
        return $root . '/config/site.config';
    }

    public static function build(string $root, ?Connection $db = null): CommandRegistry
    {
        $configPath = self::configPath($root);
        if (!is_file($configPath)) {
            throw new RuntimeException(
                'Новый стек: не найден ' . $configPath . ' (ожидается схема domains_app)',
            );
        }

        $paths = new Paths($root);
        $site = SiteConfig::fromFile($configPath);
        $writer = new SiteConfigWriter($configPath);
        $scaffolder = new Scaffolder($paths);
        $quarantine = new Quarantine();
        $resolver = new TargetResolver($site);
        $contentRefs = new ContentRefs($paths);
        $sessionGuard = new SessionGuard($paths);
        $blockEditor = new BlockFileEditor($paths);
        $workspace = new SessionWorkspace($paths);
        $contentLayout = new ContentLayout($paths);
        $trashScanner = new TrashScanner($paths);
        $providerInvoker = new ProviderInvoker($paths);

        $registry = new CommandRegistry();
        $registry->add(new HelpCommand($registry));
        $registry->add(new InitCommand(new IdentityStore($root), $root));
        $registry->add(new DomainListCommand($paths, $site));
        $registry->add(new DomainNewCommand($paths, $site, $writer, $scaffolder));
        $registry->add(new DomainBindCommand($site, $writer));
        $registry->add(new DomainUnbindCommand($site, $writer));
        $registry->add(new DomainRemoveCommand($paths, $site, $writer, $quarantine));
        $registry->add(new AlliasListCommand($paths, $site));
        $registry->add(new AlliasNewCommand($paths, $site, $writer, $scaffolder));
        $registry->add(new AlliasRemoveCommand($paths, $site, $writer, $quarantine));
        $registry->add(new PageListCommand($paths, $resolver));
        $registry->add(new PageNewCommand($paths, $resolver, $sessionGuard));
        $registry->add(new PageReadCommand($paths, $resolver));
        $registry->add(new PageWriteCommand($paths, $resolver, $contentRefs, $sessionGuard));
        $registry->add(new PageToggleCommand($paths, $resolver));
        $registry->add(new SchemeListCommand($paths, $site));
        $registry->add(new SchemeNewCommand($paths, $site));
        $registry->add(new SchemeCopyCommand($paths, $site));
        $registry->add(new SchemeSaveCommand($paths, $site, $sessionGuard));
        $registry->add(new SchemeRemoveCommand($paths, $site, $quarantine));
        $registry->add(new BlockListCommand($paths, $site));
        $registry->add(new BlockNewCommand($paths, $site));
        $registry->add(new BlockWriteCommand($paths, $site, $sessionGuard));
        $registry->add(new BlockEditCommand($paths, $site, $blockEditor));
        $registry->add(new BlockRemoveCommand($paths, $site, $quarantine));
        $registry->add(new FillerListCommand($paths, $resolver));
        $registry->add(new FillerNewCommand($paths, $resolver));
        $registry->add(new FillerWriteCommand($paths, $resolver, $sessionGuard));
        $registry->add(new FillerEditCommand($paths, $resolver, $blockEditor));
        $registry->add(new FillerToggleCommand($paths, $resolver));
        $registry->add(new SkinListCommand($paths, $resolver));
        $registry->add(new SkinNewCommand($paths, $resolver));
        $registry->add(new SkinEditCommand($paths, $resolver, $sessionGuard));
        $registry->add(new SkinRemoveCommand($paths, $resolver, $quarantine));
        $registry->add(new LangListCommand($paths, $site));
        $registry->add(new LangAddCommand($paths, $site, $writer));
        $registry->add(new LangRemoveCommand($paths, $site, $writer, $quarantine));
        $registry->add(new LangRepairCommand($paths, $site, $writer));
        $registry->add(new LangSyncCommand($paths, $site, $writer));
        $registry->add(new SessionStartCommand($paths, $site, $workspace));
        $registry->add(new SessionApplyCommand($paths, $site, $workspace));
        $registry->add(new SessionDiscardCommand($paths, $site, $workspace));
        $registry->add(new SessionListCommand($paths, $workspace));
        $registry->add(new ContentStoreCommand($paths, $resolver, $contentLayout));
        $registry->add(new ContentRemoveCommand($paths, $resolver, $contentLayout));
        $registry->add(new ContentListCommand($paths, $resolver, $contentLayout));
        $registry->add(new ContentGcCommand($paths, $contentLayout));
        $registry->add(new TrashClearCommand($paths, $trashScanner));
        $registry->add(new SystemScanCommand($paths));
        $registry->add(new SystemCleanCommand($paths, $trashScanner));
        $registry->add(new BackupCreateCommand($paths, $site));
        $registry->add(new BackupRestoreCommand($paths, $site));
        $registry->add(new DbSchemaCommand($site, $db));
        $registry->add(new DbMethodsCommand($paths, $site));
        $registry->add(new DbDescribeCommand($paths, $site));
        $registry->add(new DbCallCommand($site, $db, $providerInvoker));
        $registry->add(new DbMutateCommand($site, $db, $providerInvoker));
        $registry->add(new DbMigrateCommand($paths, $db));
        $registry->add(new DataproviderAddMethodCommand($paths, $site));
        $registry->add(new AdminPasswdCommand($paths));
        $registry->add(new UserNewCommand($db));
        $registry->add(new ApiReflectCommand($paths));
        $registry->add(new MigratePageRulesCommand($paths, $site));
        $registry->add(new NewCommand($paths, $site, $writer));
        $registry->add(new MigrateSiteConfigCommand($paths, $writer));

        return $registry;
    }
}
