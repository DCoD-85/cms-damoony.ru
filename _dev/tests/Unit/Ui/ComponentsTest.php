<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Button;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use PHPUnit\Framework\TestCase;

final class ComponentsTest extends TestCase
{
    public function testButtonSubmit(): void
    {
        $html = Button::submit('Отправить')->release();
        self::assertSame('<button class="btn btn-primary" type="submit">Отправить</button>', $html);
    }

    public function testButtonCustomClass(): void
    {
        $html = Button::button('Отмена')->class('btn btn-secondary')->release();
        self::assertStringContainsString('class="btn btn-secondary"', $html);
        self::assertStringContainsString('type="button"', $html);
    }

    public function testMessageError(): void
    {
        $html = Message::error('Что-то пошло не так')->release();
        self::assertSame('<div class="message message-error">Что-то пошло не так</div>', $html);
    }

    public function testTableBasic(): void
    {
        $t = (new Table())
            ->headers('ID', 'Название')
            ->row(['1', 'Первая'])
            ->row(['2', 'Вторая']);
        
        $html = $t->release();
        self::assertStringContainsString('<table class="table">', $html);
        self::assertStringContainsString('<th>ID</th>', $html);
        self::assertStringContainsString('<td>Вторая</td>', $html);
    }
}