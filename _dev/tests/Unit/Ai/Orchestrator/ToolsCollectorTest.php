<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ai\Orchestrator;

use Damoony\Ai\Orchestrator\ToolsCollector;
use PHPUnit\Framework\TestCase;

final class ToolsCollectorTest extends TestCase
{
    public function testParseStructureOnlyOurDomain(): void
    {
        // Реальный вывод structure:check (damoony.ru), сокращённый
        $json = [
            'ok' => false,
            'steps' => [
                ['ok' => true, 'message' => 'Алиас: damoony.ru / www — OK'],
                ['ok' => true, 'message' => 'Алиас: damoony.ru / doc — OK'],
                ['ok' => false, 'message' => 'Алиас: damoony.ru / coffee ✗'],
                ['ok' => false, 'message' => '  ✗ Папка algoritm/coffee не найдена'],
                ['ok' => false, 'message' => 'Алиас: damoony3.ru / docs ✗'],  // чужой домен — игнор
                ['ok' => false, 'message' => '  ✗ Папка algoritm/docs не найдена'],  // чужой домен
            ],
        ];

        $r = ToolsCollector::parseStructure($json, 'damoony.ru');

        self::assertFalse($r['structure_ok']);
        self::assertCount(2, $r['structure_errors']);
        self::assertStringContainsString('coffee', $r['structure_errors'][0]);
        self::assertStringNotContainsString('docs', implode(' ', $r['structure_errors']));
        self::assertSame('', $r['error']);
    }

    public function testParseStructureAllOk(): void
    {
        $json = [
            'ok' => true,
            'steps' => [
                ['ok' => true, 'message' => 'Алиас: example.ru / www — OK'],
            ],
        ];

        $r = ToolsCollector::parseStructure($json, 'example.ru');
        self::assertTrue($r['structure_ok']);
        self::assertSame([], $r['structure_errors']);
    }

    public function testParseStructureNoSteps(): void
    {
        $r = ToolsCollector::parseStructure(['ok' => false], 'damoony.ru');
        self::assertSame('structure:check: no steps in output', $r['error']);
    }

    public function testParseSchemaTables(): void
    {
        // Реальный вывод db:schema, сокращённый до name
        $json = [
            'ok' => true,
            'data' => [
                'domain' => 'damoony.ru',
                'alias' => 'www',
                'tables' => [
                    ['name' => 'audit_log', 'table' => 'audit_log', 'columns' => []],
                    ['name' => 'files', 'table' => 'files', 'columns' => []],
                    ['name' => 'news', 'table' => 'news', 'columns' => []],
                    ['name' => 'users', 'table' => 'users', 'columns' => []],
                ],
            ],
        ];

        $r = ToolsCollector::parseSchema($json);
        self::assertSame(['audit_log', 'files', 'news', 'users'], $r['db_tables']);
        self::assertSame('', $r['error']);
    }

    public function testParseSchemaFails(): void
    {
        $r = ToolsCollector::parseSchema(['ok' => false, 'error' => 'DB down']);
        self::assertSame([], $r['db_tables']);
        self::assertSame('db:schema failed: DB down', $r['error']);
    }

    public function testParseSchemaNoTables(): void
    {
        $r = ToolsCollector::parseSchema(['ok' => true, 'data' => []]);
        self::assertSame('db:schema: no data.tables in output', $r['error']);
    }
}
