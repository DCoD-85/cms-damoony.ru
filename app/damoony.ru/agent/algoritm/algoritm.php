<?php

declare(strict_types=1);

namespace damoony_ru\agent;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $this->context->meta->setTitle('AI-Агент');

        // Добавили класс center, чтобы Grid понял, куда это ставить
        $content = Builder::div('chat-wrapper center');
        $content->append(Builder::el('h2')->content('Создайте свой сайт с помощью ИИ'));

        $chatHistory = Builder::div('chat-history');
        $chatHistory->attrs()->add('id', 'chat-history');
        $chatHistory->append(
            Builder::div('chat-message ai')->content('Здравствуйте! Я помогу вам создать сайт. Расскажите, что это будет за проект?')
        );
        
        $form = Builder::el('form');
        $form->attrs()->add('id', 'chat-form');
        
        // Используем self-closing=true (третий аргумент), чтобы не было тега </input>
        $form->append(
            Builder::elA('input', ['type' => 'text', 'id' => 'chat-input', 'placeholder' => 'Напишите сообщение...', 'autocomplete' => 'off'], true)
        );
        $form->append(
            Builder::elA('button', ['type' => 'submit', 'class' => 'btn-primary'])->content('Отправить')
        );

        $content->append($chatHistory);
        $content->append($form);

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return $content;
        }

        $schemeLoader = new SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);

        $renderer = new SchemeRenderer();
        $this->setContent('center', $content);

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}