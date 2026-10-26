<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ui;

use Damoony\Ui\Field;
use Damoony\Ui\Form;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    public function testFieldText(): void
    {
        $f = Field::text('login')->label('Логин')->required()->placeholder('Введите логин');
        $html = $f->release();
        self::assertStringContainsString('<label for="field_login">Логин', $html);
        self::assertStringContainsString('<input type="text" id="field_login" name="login" value="" placeholder="Введите логин" required="required" />', $html);
    }

    public function testFieldWithError(): void
    {
        $f = Field::password('pass')->label('Пароль')->error('Неверный пароль');
        $html = $f->release();
        self::assertStringContainsString('<div class="error">Неверный пароль</div>', $html);
    }

    public function testFieldHidden(): void
    {
        $f = Field::hidden('token')->value('abc123');
        $html = $f->release();
        self::assertStringContainsString('<input type="hidden" name="token" value="abc123" />', $html);
    }

    public function testFormBasic(): void
    {
        $form = Form::create('/login')
            ->csrf('<input type="hidden" name="_csrf" value="xyz">')
            ->add(Field::text('login')->label('Логин'))
            ->add(Field::password('password')->label('Пароль'))
            ->submit('Войти');

        $html = $form->release();
        self::assertStringContainsString('<form method="POST" action="/login" class="form">', $html);
        self::assertStringContainsString('name="_csrf" value="xyz"', $html);
        self::assertStringContainsString('name="login"', $html);
        self::assertStringContainsString('name="password"', $html);
        self::assertStringContainsString('<button type="submit" class="btn btn-primary">Войти</button>', $html);
    }

    public function testFormFile(): void
    {
        $form = Form::create('/upload', 'POST')->enctype('multipart/form-data');
        $html = $form->release();
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function testFormHideSubmit(): void
    {
        $form = Form::create('/x')->hideSubmit();
        $html = $form->release();
        self::assertStringNotContainsString('<button', $html);
    }
}