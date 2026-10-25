<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\App;

use Damoony\App\FrontController;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Http\Request;
use Damoony\Routing\FileRouter;
use Damoony\Routing\RouterInterface;
use Damoony\Routing\RulesMatcher;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fc_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
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

    private function container(bool $available): Container
    {
        $site = Config::fromArray([
            'language' => 'ru-ru',
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => $available],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
            'domains_namespace' => ['example.ru' => 'example'],
        ]);

        $c = new Container();
        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($site));
        $c->setInstance(RouterInterface::class, new FileRouter($this->root, new RulesMatcher()));

        return $c;
    }

    private function req(string $path): Request
    {
        return new Request('GET', $path, headers: ['host' => 'example.ru']);
    }

    public function testUnavailableDomainReturns503(): void
    {
        $fc = new FrontController($this->container(false));
        self::assertSame(503, $fc->handle($this->req('/'))->status);
    }

    public function testUnknownRouteReturns404(): void
    {
        $fc = new FrontController($this->container(true));
        self::assertSame(404, $fc->handle($this->req('/nope'))->status);
    }

    public function testMatchedRouteReturns200(): void
    {
        $base = $this->root . '/app/alpha.0.0/domain/example.ru/allias/www/algoritm';
        mkdir($base . '/about', 0777, true);
        file_put_contents($base . '/.rules.json', json_encode(['available' => ['about']]));
        file_put_contents($base . '/about/.rules.json', json_encode(['available' => ['']]));
        file_put_contents($base . '/about/algoritm_about.php', "<?php\n");

        $fc = new FrontController($this->container(true));
        $r = $fc->handle($this->req('/about'));

        self::assertSame(200, $r->status);
        self::assertStringContainsString('algoritm_about', $r->body);
    }
}