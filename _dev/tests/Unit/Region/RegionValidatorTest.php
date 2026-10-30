<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Region;

use Damoony\Region\RegionValidator;
use PHPUnit\Framework\TestCase;

final class RegionValidatorTest extends TestCase
{
    public function testPhpBlocksDisabledByDefault(): void
    {
        $errors = (new RegionValidator())->validate([
            ['type' => 'custom', 'code' => 'echo 1;'],
        ]);

        $this->assertSame(['admin.editor.error_php_blocks_disabled'], $errors);
    }

    public function testPhpBlocksAllowedWhenEnabled(): void
    {
        $errors = (new RegionValidator(true))->validate([
            ['type' => 'if', 'condition' => '$x > 0'],
            ['type' => 'endif'],
        ]);

        $this->assertSame([], $errors);
    }

    public function testIfEmpty(): void
    {
        $errors = (new RegionValidator(true))->validate([
            ['type' => 'if', 'condition' => '   '],
            ['type' => 'endif'],
        ]);

        $this->assertContains('admin.editor.error_if_empty', $errors);
    }

    public function testEndifOrphan(): void
    {
        $errors = (new RegionValidator(true))->validate([
            ['type' => 'endif'],
        ]);

        $this->assertContains('admin.editor.error_endif_orphan', $errors);
    }

    public function testElseOrphan(): void
    {
        $errors = (new RegionValidator(true))->validate([
            ['type' => 'else'],
        ]);

        $this->assertContains('admin.editor.error_else_orphan', $errors);
    }

    public function testIfUnclosed(): void
    {
        $errors = (new RegionValidator(true))->validate([
            ['type' => 'if', 'condition' => '$x > 0'],
        ]);

        $this->assertContains('admin.editor.error_if_unclosed', $errors);
    }

    public function testSafeBlocksNoErrors(): void
    {
        $errors = (new RegionValidator())->validate([
            ['type' => 'h1', 'content' => 'Hi'],
            ['type' => 'text', 'content' => 'Body'],
        ]);

        $this->assertSame([], $errors);
    }
}
