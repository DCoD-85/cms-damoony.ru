<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\Core\Http\Response;
use Damoony\UI\Response\MinStruct;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Button;
use Damoony\Core\Security\Csrf;

final class algoritm_lang_contact extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        $csrf = $this->context->service('csrf');
        
        if ($this->isPost()) {
            $name = (string) $this->context->request->post('name', '');
            $email = (string) $this->context->request->post('email', '');
            
            $min = new MinStruct();
            if ($name === '' || $email === '') {
                $min->error(true)->messages('<div class="error">Заполните все поля</div>');
            } else {
                // Тут была бы запись в БД
                $min->error(false)
                    ->messages('<div class="success">Спасибо, ' . htmlspecialchars($name) . '! Мы свяжемся с вами.</div>')
                    ->form(true)
                    ->reload(true);
            }
            return $min->toResponse();
        }

        $form = new Form();
        $form->action('/ru/contact')->method('POST');
        
        if ($csrf instanceof Csrf) {
            $token = new Input('hidden', '_csrf');
            $token->attrs()->add('value', $csrf->token());
            $form->add($token);
        }

        $form->add(new Label('Ваше имя'));
        $form->add(new Input('text', 'name'));
        $form->add(new Label('Email'));
        $form->add(new Input('email', 'email'));
        $form->add(new Button('Отправить'));

        $wrapper = Builder::div('page-content');
        $wrapper->append(Builder::h(1)->content('Связаться с нами'));
        $wrapper->append(Builder::p()->content('Заполните форму, и мы ответим вам в ближайшее время.'));
        $wrapper->append($form);

        return $wrapper;
    }
}
