<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Core\Application;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Files\Paths;
use Damoony\Routing\FileRouter;
use Damoony\Routing\RouterInterface;
use Damoony\Routing\RulesMatcher;

final class AppFactory
{
    public static function create(string $root, ?Env $env = null): Application
    {
        $env ??= Env::fromServer();
        $configFile = $root . '/config/engine.config';
        $siteFile = $root . '/config/site.config';

        $config = is_file($configFile) ? Config::fromFile($configFile) : Config::fromArray([]);
        $site = is_file($siteFile) ? Config::fromFile($siteFile) : Config::fromArray([]);

        $container = new Container();
        $logger = new Logger(null, $env);
        $errorHandler = new ErrorHandler($logger, $env);

        $container->setInstance(Env::class, $env);
        $container->setInstance(Config::class, $config);
        $container->setInstance('site', $site);
        $container->setInstance('logger', $logger);
        $container->setInstance('errorHandler', $errorHandler);
        
        $container->setInstance(Paths::class, new Paths($root));

        $container->set(
            DomainResolverInterface::class,
            fn (): DomainResolverInterface => new ConfigDomainResolver($site),
        );

        $container->set(
            RouterInterface::class,
            fn () => new FileRouter($root, new RulesMatcher()),
        );

        $container->set(
            PageHandlerInterface::class,
            function (Container $c) use ($root): PageHandlerInterface {
                $resolver = $c->get(DomainResolverInterface::class);
                assert($resolver instanceof DomainResolverInterface);
                return new PageHandler($root, $resolver);
            },
        );

        $container->set(
            Application::class,
            fn (Container $c) => new Application($c, $env, $config, $logger, $errorHandler),
        );

        $app = $container->get(Application::class);
        assert($app instanceof Application);
        $app->boot();

        return $app;
    }
}