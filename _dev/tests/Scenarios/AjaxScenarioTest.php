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
use Damoony\Security\Csrf;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AjaxScenarioTest extends TestCase
{
    private static string $root = '';

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . '/damoony_s14_' . bin2hex(random_bytes(4));
        $dir = self::$root . '/app/alpha.0.0/domain/damoony.test/allias/www/algoritm';
        mkdir($dir . '/ajax/mode', 0777, true);
        file_put_contents($dir . '/.rules.json', json_encode(['available' => ['ajax']]));
        file_put_contents($dir . '/ajax/.rules.json', json_encode(['available' => ['([a-z]+)|mode']]));
        file_put_contents($dir . '/ajax/algoritm_ajax.php', "<?php\nnamespace damoony_test\\www;\nclass algoritm_ajax {}\n");
        file_put_contents($dir . '/ajax/mode/algoritm_ajax_mode.php', <<<'PHP'
<?php
namespace damoony_test\www;
use Damoony\Demo\Page\AjaxPage;
final class algoritm_ajax_mode extends AjaxPage {}
PHP);
    }

    public static function tearDownAfterClass(): void
    {
        self::rmrf(self::$root);
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? self::rmrf($p) : @unlink($p);
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
        $c->setInstance(Paths::class, new Paths(self::$root));
        $c->setInstance('site', $site);

        $session = new class implements SessionInterface {
            public array $data = [];
            public function start(): void {}
            public function isStarted(): bool { return true; }
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function regenerate(): void {}
            public function destroy(): void { $this->data = []; }
        };
        $c->setInstance(Csrf::class, new Csrf($session));

        $c->setInstance(AuthInterface::class, new class implements AuthInterface {
            public function isAuthenticated(): bool { return true; }
            public function user(): ?array { return null; }
            public function login(string $login, string $password): bool { return false; }
            public function logout(): void {}
        });

        $c->setInstance(DomainResolverInterface::class, new ConfigDomainResolver($site));
        $c->setInstance(RouterInterface::class, new FileRouter(self::$root, new RulesMatcher()));

        $resolver = $c->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        return new FrontController($c, new PageHandler(self::$root, $resolver));
    }

    private function req(string $path): Request
    {
        return new Request('GET', $path, headers: [
            'host' => 'damoony.test',
            'x-requested-with' => 'XMLHttpRequest',
        ]);
    }

    public function testAjaxMin(): void
    {
        $r = $this->fc()->handle($this->req('/ajax/min'));
        self::assertSame(200, $r->status);
        self::assertSame('application/json; charset=utf-8', $r->headers['content-type']);
        $data = json_decode($r->body, true);
        self::assertIsArray($data);
        self::assertFalse($data['error']);
        self::assertSame('<div class="msg">ok</div>', $data['messages']);
        self::assertSame('<div class="min-release">ajax min</div>', $data['release']);
        self::assertSame('/next', $data['form']['redirect']);
        self::assertSame(2, $data['form']['redirect_time']);
    }

    public function testAjaxLayout(): void
    {
        $r = $this->fc()->handle($this->req('/ajax/layout'));
        self::assertSame(200, $r->status);
        $data = json_decode($r->body, true);
        self::assertIsArray($data);
        self::assertSame('/ajax/layout', $data['address']);
        self::assertSame('<title>ajax</title>', $data['header']);
        self::assertCount(5, $data['layers']);
        self::assertSame('dt_center', $data['layers']['center']['layer']);
        self::assertSame('<div class="center">ajax center</div>', $data['layers']['center']['html']);
    }

    public function testNonAjaxReturnsHtml(): void
    {
        $r = $this->fc()->handle(new Request('GET', '/ajax/min', headers: ['host' => 'damoony.test']));
        self::assertSame(200, $r->status);
        self::assertStringContainsString('text/html', (string) ($r->headers['content-type'] ?? ''));
        self::assertStringContainsString('Non-ajax view', $r->body);
    }
}