<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Core\App\FrontController;
use Damoony\Core\App\PageHandler;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Domain\DomainResolverInterface;
use Damoony\Core\Domain\SiteDomainResolver;
use Damoony\Core\Http\Request;
use Damoony\Core\Routing\FileRouter;
use Damoony\Core\Routing\RouterInterface;
use Damoony\Core\Routing\RulesMatcher;
use Damoony\Tests\Support\SiteConfigFixture;
use PHPUnit\Framework\TestCase;

final class MaintenanceScenarioTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        \Damoony\Core\Error\ErrorPage::bootstrap(
            fallback: 'ru',
            langDir: dirname(__DIR__, 3) . '/lang',
        );

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
        $siteConfig = SiteConfigFixture::single('example.ru', ['www'], available: false, stub: 'maintenance');
        $site = Config::fromArray(SiteConfigFixture::legacyView('example.ru'));
        $c = new Container();
        $c->setInstance(DomainResolverInterface::class, new SiteDomainResolver($siteConfig, new \Damoony\Core\Files\Paths(sys_get_temp_dir())));
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
