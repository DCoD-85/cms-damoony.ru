<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Next\Support\ProviderInvoker;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
final class ProviderInvokerFixture
{
    public function withTypes(int $limit, string $slug = 'x', bool $flag = false): array
    {
        return [$limit, $slug, $flag];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function withArray(array $params): array
    {
        return $params;
    }
}

final class NextProviderInvokerTest extends TestCase
{
    public function testBindsPositionalArgsAndDefaults(): void
    {
        $bound = $this->invoker()->bind($this->method('withTypes'), ['limit' => 10]);

        self::assertSame(['args' => [10, 'x', false]], $bound);
    }

    public function testCastsNumericStringToInt(): void
    {
        $bound = $this->invoker()->bind($this->method('withTypes'), ['limit' => '42', 'slug' => 7]);

        self::assertSame(['args' => [42, '7', false]], $bound);
    }

    public function testRejectsUnknownParams(): void
    {
        $bound = $this->invoker()->bind($this->method('withTypes'), ['limit' => 1, 'ghost' => 2]);

        self::assertArrayHasKey('error', $bound);
        self::assertStringContainsString('Неизвестные параметры', (string) $bound['error']);
    }

    public function testRejectsMissingRequiredParam(): void
    {
        $bound = $this->invoker()->bind($this->method('withTypes'), []);

        self::assertArrayHasKey('error', $bound);
        self::assertStringContainsString('обязательный параметр', (string) $bound['error']);
    }

    public function testRejectsWrongType(): void
    {
        $bound = $this->invoker()->bind($this->method('withTypes'), ['limit' => ['not', 'int']]);

        self::assertArrayHasKey('error', $bound);
        self::assertStringContainsString('ожидает int', (string) $bound['error']);
    }

    public function testPassesWholeArrayForArraySignature(): void
    {
        $bound = $this->invoker()->bind($this->method('withArray'), ['a' => 1, 'b' => 2]);

        self::assertSame(['args' => [['a' => 1, 'b' => 2]]], $bound);
    }

    public function testParamsFromBase64(): void
    {
        $params = $this->invoker()->params([
            'params_b64' => base64_encode(json_encode(['limit' => 5])),
        ]);

        self::assertSame(['limit' => 5], $params);
    }

    public function testParamsFromPlainJson(): void
    {
        self::assertSame(['x' => 1], $this->invoker()->params(['params' => '{"x":1}']));
    }

    public function testBrokenParamsReturnNull(): void
    {
        self::assertNull($this->invoker()->params(['params' => '{broken']));
        self::assertNull($this->invoker()->params(['params_b64' => '***']));
    }

    private function invoker(): ProviderInvoker
    {
        return new ProviderInvoker(new Paths(sys_get_temp_dir()));
    }

    private function method(string $name): ReflectionMethod
    {
        return new ReflectionMethod(ProviderInvokerFixture::class, $name);
    }
}
