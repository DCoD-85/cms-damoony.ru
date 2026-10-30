<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin;

use Damoony\Admin\Controller\PageController;
use Damoony\Admin\View;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Security\Csrf;
use Damoony\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase
{
    private string $root;
    private PageController $controller;
    private Csrf $csrf;
    private string $mockToken = 'test_token_123';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pc_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
        mkdir($this->root . '/app/example.ru/.alliasscheme', 0o777, true);
        mkdir($this->root . '/lang', 0o777, true);
        file_put_contents($this->root . '/lang/ru.php', '<?php return [];');

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_scheme' => ['example.ru' => 'www'],
            'domains_namespace' => ['example.ru' => 'example'],
        ]));

        file_put_contents($this->root . '/app/example.ru/.alliasscheme/www.data.php', "<?php return ['children' => [['slot' => 'center']]];");

        $paths = new Paths($this->root);
        $auth = $this->createMock(\Damoony\Admin\AuthInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn($this->mockToken);
        $this->csrf = new Csrf($session);

        View::setLoggedIn(true);
        View::setAvailableLangs(['ru']);

        $view = new View('Test Admin');

        $this->controller = new PageController($auth, $this->csrf, $view, $paths);
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

    public function testIndexRendersTree(): void
    {
        mkdir($this->root . '/app/example.ru/www/algoritm/about');
        file_put_contents($this->root . '/app/example.ru/www/algoritm/about/.rules.json', json_encode(['auth' => true]));

        $req = new Request('GET', '/admin/?p=page&domain=example.ru&allias=www', ['p' => 'page', 'domain' => 'example.ru', 'allias' => 'www']);
        $response = $this->controller->index($req);

        self::assertSame(200, $response->status);
    }
}
