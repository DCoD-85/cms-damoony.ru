<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
use Damoony\Cli\Db\Commands\SchemaAddColumnCommand;
use Damoony\Cli\Db\Commands\SchemaGenerateProviderCommand;
use Damoony\Cli\Db\Commands\SchemaGenerateSqlCommand;
use Damoony\Cli\Db\Commands\SchemaInitCommand;
use Damoony\Cli\Renderer;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$paths = new Paths($root);

$siteFile = $root . '/config/site.config';
if (!is_file($siteFile)) {
    fwrite(STDERR, "dbcli: не найден config/site.config\n");
    exit(1);
}

try {
    $site = SiteConfig::fromFile($siteFile);
} catch (Throwable $e) {
    fwrite(STDERR, 'dbcli: ' . $e->getMessage() . "\n");
    exit(1);
}

$registry = new CommandRegistry();
$registry->add(new SchemaInitCommand($paths));
$registry->add(new SchemaAddColumnCommand($paths));
$registry->add(new SchemaGenerateSqlCommand($paths, $site));
$registry->add(new SchemaGenerateProviderCommand($paths, $site));

$app = new Application($registry, new ArgvParser(), new Renderer());

$argv = $_SERVER['argv'] ?? [];
exit($app->run($argv));
