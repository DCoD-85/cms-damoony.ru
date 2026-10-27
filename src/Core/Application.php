<?php

declare(strict_types=1);

namespace Damoony\Core;

use Psr\Log\LoggerInterface;

final class Application
{
    public function __construct(
        private readonly Container $container,
        private readonly Env $env,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ErrorHandler $errorHandler,
    ) {
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function env(): Env
    {
        return $this->env;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function errorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }

    public function boot(): void
    {
        $this->errorHandler->register();
        $this->logger->info('Application booted', ['env' => $this->env->value]);
    }
}
