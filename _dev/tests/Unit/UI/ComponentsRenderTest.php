<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI;

use Damoony\UI\Components\Button;
use Damoony\UI\Components\Img;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Textarea;
use PHPUnit\Framework\TestCase;

final class ComponentsRenderTest extends TestCase
{
    public function testButton(): void
    {
        $html = new Button('Отправить')->release();

        self::assertSame('<button class="ui-btn" type="submit">Отправить</button>', $html);
    }

    public function testButtonType(): void
    {
        $html = new Button('Отмена', 'button')->release();

        self::assertStringContainsString('type="button"', $html);
    }

    public function testInput(): void
    {
        $html = new Input('password', 'password')->release();

        self::assertSame('<input class="ui-input" type="password" name="password" />', $html);
    }

    public function testInputHiddenWithValue(): void
    {
        $html = new Input('hidden', 'token', 'abc')->release();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="token"', $html);
        self::assertStringContainsString('value="abc"', $html);
    }

    public function testLabel(): void
    {
        $html = new Label('Логин', 'login')->release();

        self::assertSame('<label class="ui-label" for="login">Логин</label>', $html);
    }

    public function testLink(): void
    {
        $html = new Link('/ru/about/', 'О нас')->release();

        self::assertSame('<a class="ui-link" href="/ru/about/">О нас</a>', $html);
    }

    public function testImg(): void
    {
        $html = new Img('/img.png', 'Логотип')->release();

        self::assertSame('<img class="ui-img" src="/img.png" alt="Логотип" />', $html);
    }

    public function testTextarea(): void
    {
        $html = new Textarea('body', 'Текст', 5)->release();

        self::assertStringContainsString('<textarea class="ui-textarea" rows="5" name="body">', $html);
        self::assertStringContainsString('Текст</textarea>', $html);
    }

    public function testSelectWithSelectedOption(): void
    {
        $select = new Select('city');
        $select->add(new Option('msk', 'Москва'));
        $select->add(new Option('spb', 'Питер', true));

        $html = $select->release();

        self::assertStringContainsString('<select class="ui-select" name="city">', $html);
        self::assertStringContainsString('<option value="msk">Москва</option>', $html);
        self::assertStringContainsString('<option value="spb" selected="selected">Питер</option>', $html);
    }
}
