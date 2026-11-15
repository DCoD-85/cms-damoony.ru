<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Response;

use Damoony\UI\Builder;
use Damoony\UI\Response\AjaxStruct;
use Damoony\UI\Response\MinStruct;
use PHPUnit\Framework\TestCase;

final class AjaxMinStructTest extends TestCase
{
    public function testMinDefaults(): void
    {
        $m = new MinStruct();
        $a = $m->toArray();

        self::assertTrue($a['ok']);
        self::assertSame([], $a['messages']);
        self::assertSame([], $a['blocks']);
        self::assertSame(['reset' => false, 'keep' => [], 'redirect' => null, 'redirect_time' => null], $a['form']);
        self::assertFalse($a['reload']);
    }

    public function testMinFilled(): void
    {
        $m = new MinStruct();
        $m->error(true)
            ->message('error', 'err')
            ->release(Builder::div('x')->content('y'))
            ->form(true, [], '/login', 3);

        $a = $m->toArray();
        self::assertFalse($a['ok']);
        self::assertSame([['type' => 'error', 'text' => 'err']], $a['messages']);
        self::assertSame(['center' => '<div class="x">y</div>'], $a['blocks']);
        self::assertSame(['reset' => true, 'keep' => [], 'redirect' => '/login', 'redirect_time' => 3], $a['form']);
    }


    public function testMinToResponse(): void
    {
        $r = new MinStruct()->error(true)->toResponse(422);
        self::assertSame(422, $r->status);
        self::assertSame('application/json; charset=utf-8', $r->headers['content-type']);
    }

    public function testAjaxDefaults(): void
    {
        $a = new AjaxStruct()->toArray();
        self::assertFalse($a['error']);
        self::assertSame('', $a['address']);
        self::assertSame('', $a['header']);
        self::assertCount(5, $a['layers']);
        self::assertSame(['layer' => 'dt_top', 'html' => ''], $a['layers']['top']);
    }

    public function testAjaxFilled(): void
    {
        $s = new AjaxStruct()
            ->address('/x')
            ->header('<h1>h</h1>')
            ->center('<p>c</p>')
            ->error(true);

        $a = $s->toArray();
        self::assertTrue($a['error']);
        self::assertSame('/x', $a['address']);
        self::assertSame('<h1>h</h1>', $a['header']);
        self::assertSame(['layer' => 'dt_center', 'html' => '<p>c</p>'], $a['layers']['center']);
    }

    public function testAjaxUnknownLayerIgnored(): void
    {
        $s = new AjaxStruct()->layer('nonexistent', 'x');
        self::assertArrayNotHasKey('nonexistent', $s->toArray()['layers']);
    }

    public function testAjaxToResponse(): void
    {
        $r = new AjaxStruct()->toResponse();
        self::assertSame(200, $r->status);
        self::assertSame('application/json; charset=utf-8', $r->headers['content-type']);
    }
}
