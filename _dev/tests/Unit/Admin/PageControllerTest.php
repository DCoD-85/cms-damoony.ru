<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin;

use Damoony\Admin\Controller\PageController;
use Damoony\Admin\View;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase
{
    private string $root;
    private PageController $controller;
    private Csrf $csrf;

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

        $this->csrf = $this->createMock(Csrf::class);
        $this->csrf->method('field')->willReturn('<input type="hidden" name="_csrf" value="mock">');
        $this->csrf->method('check')->willReturn(true);

        $view = new View(new \Damoony\I18n\Translator('ru', 'ru', $this->root . '/lang'));

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

        $req = new Request('GET', '/admin/?p=page&domain=example.ru&allias=www');
        $response = $this->controller->index($req);

        $body = $response->body;
        self::assertStringContainsString('Корень (/)', $body);
        self::assertStringContainsString('/about', $body);
    }

    public function testSaveUpdatesRulesAndJson(): void
    {
        mkdir($this->root . '/app/example.ru/www/algoritm/about');
        file_put_contents($this->root . '/app/example.ru/www/algoritm/about/.rules.json', json_encode(['auth' => false]));

        $json = json_encode([
            'slots' => ['center' => ['status' => 'local', 'blocks' => [['type' => 'h1', 'content' => 'Test']]]],
            'auth' => true,
        ]);

        $req = new Request('POST', '/admin/?p=page:save&domain=example.ru&allias=www&path=about', [
            'page_json' => $json,
        ]);

        $response = $this->controller->save($req);
        self::assertSame(302, $response->status);

        $editorFile = $this->root . '/app/example.ru/www/algoritm/about/page.editor.json';
        self::assertFileExists($editorFile);
        $data = json_decode(file_get_contents($editorFile), true);
        self::assertTrue($data['auth']);

        $rulesFile = $this->root . '/app/example.ru/www/algoritm/about/.rules.json';
        $rules = json_decode(file_get_contents($rulesFile), true);
        self::assertTrue($rules['auth']);

        $phpFile = $this->root . '/app/example.ru/www/algoritm/about/algoritm_about.php';
        self::assertFileExists($phpFile);
        $phpContent = file_get_contents($phpFile);
        self::assertStringContainsString("Builder::h(1)->content('Test')", $phpContent);
    }
}
