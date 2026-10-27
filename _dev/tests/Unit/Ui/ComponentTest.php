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
        self::assertStringContainsString('<form class="form" method="POST" action="/login">', $html);
        self::assertStringContainsString('name="_csrf" value="xyz"', $html);
        self::assertStringContainsString('name="login"', $html);
        self::assertStringContainsString('name="password"', $html);
        self::assertStringContainsString('<button class="btn btn-primary" type="submit">Войти</button>', $html);
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

    public function testFieldTextarea(): void
    {
        $f = Field::textarea('desc')->rows(5)->cols(30)->value('Hello');
        $html = $f->release();
        self::assertStringContainsString('<textarea id="field_desc" name="desc" rows="5" cols="30">Hello</textarea>', $html);
    }

    public function testFieldSelect(): void
    {
        $f = Field::select('category')
            ->options(['a' => 'Alpha', 'b' => 'Beta'])
            ->selected('b')
            ->placeholderOption('Выберите');
        $html = $f->release();
        self::assertStringContainsString('<option value="">Выберите</option>', $html);
        self::assertStringContainsString('<option value="a">Alpha</option>', $html);
        self::assertStringContainsString('<option value="b" selected="selected">Beta</option>', $html);
    }

    public function testFieldCheckbox(): void
    {
        $f = Field::checkbox('agree')->label('Согласен')->value('1')->checked();
        $html = $f->release();
        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString('value="1"', $html);
        self::assertStringContainsString('checked="checked"', $html);
        self::assertStringContainsString('<label for="field_agree_1">Согласен</label>', $html);
    }

    public function testFieldRadio(): void
    {
        $f = Field::radio('gender')->value('m')->label('М')->checked();
        $html = $f->release();
        self::assertStringContainsString('type="radio"', $html);
        self::assertStringContainsString('id="field_gender_m"', $html);
        self::assertStringContainsString('<label for="field_gender_m">М</label>', $html);
    }

    public function testFieldFile(): void
    {
        $f = Field::file('avatar')->accept('image/*')->label('Аватар');
        $html = $f->release();
        self::assertStringContainsString('type="file"', $html);
        self::assertStringContainsString('accept="image/*"', $html);
    }
}
