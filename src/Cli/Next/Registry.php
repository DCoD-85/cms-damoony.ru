<?php

declare(strict_types=1);

namespace Damoony\Cli\Next;

use Damoony\Cli\CommandRegistry;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Next\Commands\AlliasListCommand;
use Damoony\Cli\Next\Commands\AlliasNewCommand;
use Damoony\Cli\Next\Commands\AlliasRemoveCommand;
use Damoony\Cli\Next\Commands\DomainBindCommand;
use Damoony\Cli\Next\Commands\DomainListCommand;
use Damoony\Cli\Next\Commands\DomainNewCommand;
use Damoony\Cli\Next\Commands\DomainRemoveCommand;
use Damoony\Cli\Next\Commands\DomainUnbindCommand;
use Damoony\Cli\Next\Commands\PageListCommand;
use Damoony\Cli\Next\Commands\PageNewCommand;
use Damoony\Cli\Next\Commands\PageReadCommand;
use Damoony\Cli\Next\Commands\PageToggleCommand;
use Damoony\Cli\Next\Commands\PageWriteCommand;
use Damoony\Cli\Next\Commands\SchemeCopyCommand;
use Damoony\Cli\Next\Commands\SchemeListCommand;
use Damoony\Cli\Next\Commands\SchemeNewCommand;
use Damoony\Cli\Next\Commands\SchemeRemoveCommand;
use Damoony\Cli\Next\Scaffold\Scaffolder;
use Damoony\Cli\Next\Support\ContentRefs;
use Damoony\Cli\Next\Support\Quarantine;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
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

    public static function build(string $root): CommandRegistry
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

        $registry = new CommandRegistry();
        $registry->add(new HelpCommand($registry));
        $registry->add(new DomainListCommand($paths, $site));
        $registry->add(new DomainNewCommand($paths, $site, $writer, $scaffolder));
        $registry->add(new DomainBindCommand($site, $writer));
        $registry->add(new DomainUnbindCommand($site, $writer));
        $registry->add(new DomainRemoveCommand($paths, $site, $writer, $quarantine));
        $registry->add(new AlliasListCommand($paths, $site));
        $registry->add(new AlliasNewCommand($paths, $site, $writer, $scaffolder));
        $registry->add(new AlliasRemoveCommand($paths, $site, $writer, $quarantine));
        $registry->add(new PageListCommand($paths, $resolver));
        $registry->add(new PageNewCommand($paths, $resolver));
        $registry->add(new PageReadCommand($paths, $resolver));
        $registry->add(new PageWriteCommand($paths, $resolver, $contentRefs));
        $registry->add(new PageToggleCommand($paths, $resolver));
        $registry->add(new SchemeListCommand($paths, $site));
        $registry->add(new SchemeNewCommand($paths, $site));
        $registry->add(new SchemeCopyCommand($paths, $site));
        $registry->add(new SchemeRemoveCommand($paths, $site, $quarantine));

        return $registry;
    }
}
