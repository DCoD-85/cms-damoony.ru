<?php

declare(strict_types=1);

namespace damoony_ru\development;

use Damoony\UI\Algoritm\PageWithScheme;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_engine extends PageWithScheme
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
        
        $btns->append(Builder::button('button', 'ui-btn btn-success', 'btn-toast-success')->content('Успех'));
        $btns->append(Builder::button('button', 'ui-btn btn-error', 'btn-toast-error')->content('Ошибка'));
        $btns->append(Builder::button('button', 'ui-btn btn-warning', 'btn-toast-warning')->content('Внимание'));
        $btns->append(Builder::button('button', 'ui-btn btn-info', 'btn-toast-info')->content('Инфо'));
        
        $content->append($btns);

        // Кнопки для теста Fetch / API
        $content->append(Builder::h(2)->content('2. Тест API запросов'));
        $api_btns = Builder::div('test-buttons');
        
        $api_btns->append(Builder::button('button', 'ui-btn', 'btn-fetch-get')->content('Запрос GET (успешный)'));
        $api_btns->append(Builder::button('button', 'ui-btn', 'btn-fetch-post')->content('Запрос POST (ошибка 422)'));
        
        $content->append($api_btns);

        return $content;
    }
}
