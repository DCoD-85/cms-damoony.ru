<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\App;

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
        $c->setInstance(\Damoony\Files\Paths::class, new \Damoony\Files\Paths($this->root));
        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($site));
        $c->setInstance(RouterInterface::class, new FileRouter($this->root, new RulesMatcher()));

        return $c;
    }

    private function fc(bool $available): FrontController
    {
        $c = $this->container($available);
        $resolver = $c->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        return new FrontController($c, new PageHandler($this->root, $resolver));
    }

    private function req(string $path): Request
    {
        return new Request('GET', $path, headers: ['host' => 'example.ru']);
    }

    public function testUnavailableDomainReturns503(): void
    {
        self::assertSame(503, $this->fc(false)->handle($this->req('/'))->status);
    }

    public function testUnknownRouteReturns404(): void
    {
        self::assertSame(404, $this->fc(true)->handle($this->req('/nope'))->status);
    }

    public function testMatchedRouteRendersPage(): void
    {
        $base = $this->root . '/app/alpha.0.0/domain/example.ru/allias/www/algoritm';
        mkdir($base . '/fc_home', 0777, true);
        file_put_contents($base . '/.rules.json', json_encode(['available' => ['fc_home']]));
        file_put_contents($base . '/fc_home/.rules.json', json_encode(['available' => ['']]));
        file_put_contents($base . '/fc_home/algoritm_fc_home.php', <<<'PHP'
<?php
namespace example\www;
use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
final class algoritm_fc_home extends Algoritm {
    public function default(): Node|Response|null {
        return Builder::div('page')->content('home');
    }
}
PHP);

        $r = $this->fc(true)->handle($this->req('/fc_home'));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('<div class="page">home</div>', $r->body);
    }
}