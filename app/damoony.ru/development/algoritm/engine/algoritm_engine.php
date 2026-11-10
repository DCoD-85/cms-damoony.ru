<?php

declare(strict_types=1);

namespace damoony_ru\development;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_engine extends Page
{
    protected function content(): Node
    {
        $this->context->meta->addJs('/media/.admin/superadmin/skin/default/js/cliengine.js');
        $this->context->meta->addJs('/media/.admin/superadmin/skin/default/js/test_engine.js');

        $content = Builder::div('engine-test-wrapper');
        
        $content->append(Builder::h(1)->content('🧪 Тестовый полигон CLIEngine'));

        // Кнопки для теста тостов
        $content->append(Builder::h(2)->content('1. Тест всплывающих плашек (Toasts)'));
        $btns = Builder::div('test-buttons');
        
        $btn1 = Builder::button('button', 'ui-btn btn-success')->content('Успех');
        $btn1->attrs()->add('id', 'btn-toast-success');
        $btns->append($btn1);

        $btn2 = Builder::button('button', 'ui-btn btn-error')->content('Ошибка');
        $btn2->attrs()->add('id', 'btn-toast-error');
        $btns->append($btn2);

        $btn3 = Builder::button('button', 'ui-btn btn-warning')->content('Внимание');
        $btn3->attrs()->add('id', 'btn-toast-warning');
        $btns->append($btn3);

        $btn4 = Builder::button('button', 'ui-btn btn-info')->content('Инфо');
        $btn4->attrs()->add('id', 'btn-toast-info');
        $btns->append($btn4);
        
        $content->append($btns);

        // Кнопки для теста Fetch / API
        $content->append(Builder::h(2)->content('2. Тест API запросов'));
        $api_btns = Builder::div('test-buttons');
        
        $btnGet = Builder::button('button', 'ui-btn')->content('Запрос GET (успешный)');
        $btnGet->attrs()->add('id', 'btn-fetch-get');
        $api_btns->append($btnGet);

        $btnPost = Builder::button('button', 'ui-btn')->content('Запрос POST (ошибка 422)');
        $btnPost->attrs()->add('id', 'btn-fetch-post');
        $api_btns->append($btnPost);
        
        $content->append($api_btns);

        return $content;
    }
}
