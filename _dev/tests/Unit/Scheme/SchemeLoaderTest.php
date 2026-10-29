<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Scheme;

use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SchemeLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_sl_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->root);
        putenv('APP_ENV');
        unset($_ENV['APP_ENV']);
    }

    private function domain(): DomainContext
    {
        return new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            skin: 'default',
            language: 'ru',
            available: true,
            stub: null,
            namespace: 'example',
            scheme: 'www',
        );
    }

    public function testThrowsInProdWhenSchemeMissing(): void
    {
        putenv('APP_ENV=prod');
        $_ENV['APP_ENV'] = 'prod';

        $loader = new SchemeLoader(new Paths($this->root));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scheme not found:');

        $loader->load($this->domain(), 'www');
    }

    public function testReturnsFallbackInDevWhenSchemeMissing(): void
    {
        putenv('APP_ENV=dev');
        $_ENV['APP_ENV'] = 'dev';

        $loader = new SchemeLoader(new Paths($this->root));

        $data = $loader->load($this->domain(), 'www');

        self::assertSame('div', $data['tag']);
        self::assertSame('layout', $data['class']);
    }
}
