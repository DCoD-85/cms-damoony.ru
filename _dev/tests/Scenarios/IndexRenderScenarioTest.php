<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\App\FrontController;
use Damoony\App\PageHandler;
use Damoony\Auth\AuthInterface;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Domain\ConfigDomainResolver;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Routing\FileRouter;
use Damoony\Routing\RouterInterface;
use Damoony\Routing\RulesMatcher;
use PHPUnit\Framework\TestCase;

final class IndexRenderScenarioTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_s11_' . bin2hex(random_bytes(4));
        $dir = $this->root . '/app/alpha.0.0/domain/damoony.test/allias/www/algoritm';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.rules.json', json_encode(['available' => ['']]));
        file_put_contents($dir . '/algoritm.php', <<<'PHP'
<?php
namespace damoony_test\www;
use Damoony\Demo\Page\HomePage;
final class algoritm extends HomePage {}
PHP);

        $globalDir = $this->root . '/app/alpha.0.0/domain/damoony.test/alliasglobal';
        mkdir($globalDir . '/hello', 0777, true);
        mkdir($globalDir . '/footer', 0777, true);

        file_put_contents($globalDir . '/hello/hello.php', <<<'PHP'
<?php
namespace damoony_test;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
final class alliasglobal_hello implements RegionInterface {
    public function name(): string { return 'hello'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node {
        return Builder::div('hello')->content('Hello from test');
    }
}
PHP);

        file_put_contents($globalDir . '/footer/footer.php', <<<'PHP'
<?php
namespace damoony_test;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
final class alliasglobal_footer implements RegionInterface {
    public function name(): string { return 'footer'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node {
        return Builder::div('footer')->content('Footer from test');
    }
}
PHP);

        $schemeDir = $this->root . '/app/alpha.0.0/domain/damoony.test/alliasscheme';
        mkdir($schemeDir, 0777, true);
        file_put_contents($schemeDir . '/www.data.php', <<<'PHP'
<?php
return [
    'tag' => 'div',
    'class' => 'layout',
    'children' => [
        ['tag' => 'section', 'slot' => 'hello'],
        ['tag' => 'footer', 'slot' => 'footer'],
    ],
];
PHP);
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
            'domains_real' => ['damoony.test' => 'damoony.test'],
            'domains_available' => ['damoony.test' => true],
            'alliases_real' => ['damoony.test' => ['www' => 'www']],
            'domains_namespace' => ['damoony.test' => 'damoony_test'],
        ]);
        $c = new Container();
        $c->setInstance(Paths::class, new Paths($this->root));
        $c->setInstance('site', $site);
        
        $session = new class implements \Damoony\Session\SessionInterface {
            public array $data = [];
            public function start(): void {}
            public function isStarted(): bool { return true; }
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function regenerate(): void {}
            public function destroy(): void { $this->data = []; }
        };
        $c->setInstance(\Damoony\Security\Csrf::class, new \Damoony\Security\Csrf($session));

        $c->setInstance(\Damoony\Auth\AuthInterface::class, new class implements \Damoony\Auth\AuthInterface {
            public function isAuthenticated(): bool { return true; }
            public function user(): ?array { return null; }
            public function login(string $login, string $password): bool { return false; }
            public function logout(): void {}
        });
        
        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($site));
        $c->setInstance(RouterInterface::class, new FileRouter($this->root, new RulesMatcher()));
        
        $resolver = $c->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        return new FrontController($c, new PageHandler($this->root, $resolver));
    }

    public function testIndexRendersRegionsViaScheme(): void
    {
        $r = $this->fc()->handle(new Request('GET', '/', headers: ['host' => 'damoony.test']));

        self::assertSame(200, $r->status);
        self::assertStringContainsString('<!DOCTYPE html>', $r->body);
        self::assertStringContainsString('lang="ru-ru"', $r->body);
        self::assertStringContainsString('<div class="hello">Hello from test</div>', $r->body);
        self::assertStringContainsString('<div class="footer">Footer from test</div>', $r->body);
    }
}