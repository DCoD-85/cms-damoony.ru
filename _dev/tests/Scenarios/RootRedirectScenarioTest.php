<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Корень алиаса www: редирект на язык проекта по умолчанию.
 *
 * Проверяем реальный файл страницы и то, что сборка developer содержит
 * ту же логику (иначе `new --assembly` вернёт старый хардкод «/ru/»).
 */
final class RootRedirectScenarioTest extends TestCase
{
    private const RUNTIME = '/app/damoony.ru/www/algoritm/algoritm.php';
    private const STUB = '/stubs/assemblies/developer/app/www/algoritm/algoritm.php';

    public static function setUpBeforeClass(): void
    {
        $file = dirname(__DIR__, 3) . self::RUNTIME;
        if (!is_file($file)) {
            self::markTestSkipped('Нет локального проекта damoony.ru — сценарий применим только к нему');
        }

        require_once $file;
    }

    public function testRedirectsToProjectLanguage(): void
    {
        $context = new RegionContext(
            new Request('GET', '/'),
            new DomainContext('damoony.ru', 'damoony.ru', 'www', 'default', 'en', true),
        );

        $page = new \damoony_ru\www\algoritm($context, new RegionRegistry());
        $response = $page->default();

        self::assertNotNull($response);
        self::assertSame(301, $response->status);
        self::assertSame('/en/', $response->headers['location'] ?? null);
    }

    public function testAssemblyStubMatchesRuntimePage(): void
    {
        $root = dirname(__DIR__, 3);
        $runtime = (string) file_get_contents($root . self::RUNTIME);
        $stub = (string) file_get_contents($root . self::STUB);

        // Сборка отличается только плейсхолдером namespace.
        self::assertSame(
            str_replace('namespace damoony_ru\www;', 'namespace {{namespace}}\www;', $runtime),
            $stub,
        );
    }

    public function testRootRulesDisableSearch(): void
    {
        $root = dirname(__DIR__, 3);
        $rules = json_decode((string) file_get_contents($root . '/app/damoony.ru/www/algoritm/algoritm.rules.json'), true);

        self::assertIsArray($rules);
        self::assertFalse($rules['search'] ?? null);
    }
}
