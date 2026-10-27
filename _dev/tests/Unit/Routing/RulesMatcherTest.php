<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Routing;

use Damoony\Routing\RulesMatcher;
use PHPUnit\Framework\TestCase;

final class RulesMatcherTest extends TestCase
{
    private RulesMatcher $m;

    protected function setUp(): void
    {
        $this->m = new RulesMatcher();
    }

    public function testEmptySegment(): void
    {
        self::assertSame(['', []], $this->m->matchSegment('', ['available' => ['']]));
    }

    public function testExactMatch(): void
    {
        self::assertSame(['about', []], $this->m->matchSegment('about', ['available' => ['about']]));
    }

    public function testRegexWithParam(): void
    {
        self::assertSame(
            ['slug', ['slug' => 'hello-world']],
            $this->m->matchSegment('hello-world', ['available' => ['([a-z0-9-]+)|slug']]),
        );
    }

    public function testNoMatchReturnsNull(): void
    {
        self::assertNull($this->m->matchSegment('nope', ['available' => ['about']]));
    }

    public function testInvalidAvailableReturnsNull(): void
    {
        self::assertNull($this->m->matchSegment('x', ['available' => 'not-array']));
        self::assertNull($this->m->matchSegment('x', []));
    }

    public function testPipeWithoutRegexIgnored(): void
    {
        self::assertNull($this->m->matchSegment('x', ['available' => ['target|']]));
    }

    public function testAuthFlag(): void
    {
        self::assertTrue($this->m->requiresAuth(['auth' => true]));
        self::assertFalse($this->m->requiresAuth(['auth' => false]));
        self::assertFalse($this->m->requiresAuth([]));
    }
}
