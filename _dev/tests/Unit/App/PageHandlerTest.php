<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\App;

use Damoony\App\PageHandler;
use Damoony\Domain\DomainContext;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Http\Request;
use Damoony\Region\RegionContext;
use Damoony\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;

final class PageHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_ph_' . bin2hex(random_bytes(4));
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

    private function resolver(): DomainResolverInterface
    {
        return new class implements DomainResolverInterface {
            public function resolve(string $host): DomainContext
            {
                throw new \LogicException('not used');
            }

            public function exists(string $domain): bool
            {
                return true;
            }

            public function real(string $domain): string
            {
                return $domain;
            }

            public function namespace(string $domain): string
            {
                return 'example';
            }
        };
    }

    private function ctx(): RegionContext
    {
        $paths = new \Damoony\Files\Paths(sys_get_temp_dir());
        
        return new RegionContext(
            new Request('GET', '/'),
            new DomainContext('example.ru', 'example.ru', 'www', 'alpha.0.0', 'default', 'ru-RU', true),
            null,
            ['paths' => $paths]
        );
    }

    private function writePage(string $rel, string $chain, string $code): void
    {
        $dir = $this->root . '/app/alpha.0.0/domain/example.ru/allias/www/algoritm'
            . ($rel === '' ? '' : '/' . $rel);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $chain . '.php', $code);
    }

    public function testRendersNodeViaDocument(): void
    {
        $this->writePage('ph_about', 'algoritm_ph_about', <<<'PHP'
<?php
namespace example\www;
use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
final class algoritm_ph_about extends Algoritm {
    public function default(): Node|Response|null {
        return Builder::div('page')->content('about');
    }
}
PHP);

        $h = new PageHandler($this->root, $this->resolver());
        $r = $h->handle(RouteMatch::found('algoritm_ph_about', 'ph_about'), $this->ctx());

        self::assertSame(200, $r->status);
        self::assertStringContainsString('<div class="page">about</div>', $r->body);
    }

    public function testReturnsResponseDirectly(): void
    {
        $this->writePage('ph_api', 'algoritm_ph_api', <<<'PHP'
<?php
namespace example\www;
use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Node;
final class algoritm_ph_api extends Algoritm {
    public function default(): Node|Response|null {
        return Response::json(['ok' => true]);
    }
}
PHP);

        $h = new PageHandler($this->root, $this->resolver());
        $r = $h->handle(RouteMatch::found('algoritm_ph_api', 'ph_api'), $this->ctx());

        self::assertSame(200, $r->status);
        self::assertSame('application/json; charset=utf-8', $r->headers['content-type']);
    }

    public function testMissingFileReturns404(): void
    {
        $h = new PageHandler($this->root, $this->resolver());
        $r = $h->handle(RouteMatch::found('algoritm_ph_nope', 'ph_nope'), $this->ctx());
        self::assertSame(404, $r->status);
    }

    public function testNullResultIsError(): void
    {
        $this->writePage('ph_nul', 'algoritm_ph_nul', <<<'PHP'
<?php
namespace example\www;
use Damoony\Algoritm\Algoritm;
final class algoritm_ph_nul extends Algoritm {
}
PHP);

        $h = new PageHandler($this->root, $this->resolver());
        $r = $h->handle(RouteMatch::found('algoritm_ph_nul', 'ph_nul'), $this->ctx());
        self::assertSame(500, $r->status);
    }
}