<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Builder;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Td;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;

final class algoritm_ui extends Page
{
    public function default(): Node|Response|null
    {
        // 1. Создаем форму (обертка div.form_box + form.form_form)
        $form = (new Form())->action('/submit')->method('POST');

        // 2. Создаем таблицу для выравнивания полей
        $table = new Table();
        $tbody = new Tbody();

        // Строка 1: Выбор города
        $tr1 = new Tr();
        $tr1->add((new Td())->add(Builder::span()->content('Город:')));
        
        $select = new Select('city');
        $select->add(new Option('msk', 'Москва', true));
        $select->add(new Option('spb', 'Санкт-Петербург'));
        
        $tr1->add((new Td())->add($select));
        $tbody->add($tr1);

        // Строка 2: Ввод имени
        $tr2 = new Tr();
        $tr2->add((new Td())->add(Builder::span()->content('Имя:')));
        $tr2->add((new Td())->add(new Input('text', 'username')));
        $tbody->add($tr2);

        // Строка 3: Кнопка отправки
        $tr3 = new Tr();
        $tr3->add((new Td())->add(\Damoony\UI\Builder::span())); // Пустая ячейка
        $tr3->add((new Td())->add(new Button('Отправить')));
        $tbody->add($tr3);

        // 3. Собираем конструкцию
        $table->add($tbody);
        $form->add($table);

        // 4. Возвращаем на страницу
        $content = Builder::div('page-content');
        $content->append(Builder::h(1)->content('Тест UI Компонентов'));
        $content->append($form);

        return $content;
    }
}