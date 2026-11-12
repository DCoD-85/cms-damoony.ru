<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\I18n;

use Damoony\Core\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorPluralTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/damoony_i18n_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o777, true);
        file_put_contents($this->dir . '/ru.json', json_encode([
            'item.one' => '{{amount}} товар',
            'item.few' => '{{amount}} товара',
            'item.many' => '{{amount}} товаров',
            'other.other' => '{{amount}} штук'
        ], JSON_UNESCAPED_UNICODE));
        file_put_contents($this->dir . '/en.json', json_encode([
            'item.one' => '{{amount}} item',
            'item.other' => '{{amount}} items'
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/ru.json');
        @unlink($this->dir . '/en.json');
        @rmdir($this->dir);
    }

    public function testRussianPluralization(): void
    {
        $t = new Translator('ru', 'en', $this->dir, [$this->dir]);
        
        $this->assertSame('1 товар', $t->tPlural('item', 1));
        $this->assertSame('2 товара', $t->tPlural('item', 2));
        $this->assertSame('5 товаров', $t->tPlural('item', 5));
        $this->assertSame('11 товаров', $t->tPlural('item', 11));
        $this->assertSame('21 товар', $t->tPlural('item', 21));
        $this->assertSame('0 товаров', $t->tPlural('item', 0));
    }

    public function testEnglishPluralization(): void
    {
        $t = new Translator('en', null, $this->dir, [$this->dir]);
        
        $this->assertSame('1 item', $t->tPlural('item', 1));
        $this->assertSame('2 items', $t->tPlural('item', 2));
        $this->assertSame('0 items', $t->tPlural('item', 0));
    }

    public function testFallbackToOther(): void
    {
        $t = new Translator('ru', null, $this->dir, [$this->dir]);
        // other.other не имеет ru специфики, используется .other
        $this->assertSame('5 штук', $t->tPlural('other', 5));
    }
}
