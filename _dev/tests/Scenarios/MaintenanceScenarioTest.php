<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\App\FrontController;
use Damoony\App\PageHandler;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Http\Request;
use Damoony\Routing\FileRouter;
use Damoony\Routing\RouterInterface;
use Damoony\Routing\RulesMatcher;
use PHPUnit\Framework\TestCase;

final class MaintenanceScenarioTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_s13_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o777, true);
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

    private function fc(): FrontController
    {
        $site = Config::fromArray([
            'language' => 'ru-ru',
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => false],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
            'domains_namespace' => ['example.ru' => 'example'],
            'domains_stub' => ['example.ru' => ['current' => 'maintenance']],
        ]);
        $c = new Container();
        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($site));
        $c->setInstance(RouterInterface::class, new FileRouter($this->root, new RulesMatcher()));
        $resolver = $c->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        return new FrontController($c, new PageHandler($this->root, $resolver));
    }

    public function testUnavailableDomainReturnsHtml503(): void
    {
        $r = $this->fc()->handle(new Request('GET', '/', headers: ['host' => 'example.ru']));

        self::assertSame(503, $r->status);
        self::assertStringContainsString('text/html', (string) ($r->headers['content-type'] ?? ''));
        self::assertStringContainsString('<!DOCTYPE html>', $r->body);
        self::assertStringContainsString('class="error error-503"', $r->body);
        self::assertStringContainsString('Технические работы', $r->body);
    }
}
