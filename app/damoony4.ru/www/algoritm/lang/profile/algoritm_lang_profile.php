<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_profile extends Page
{
    protected function content(): Node|Response|null
    {
        if (!$this->isAuthenticated()) {
            return Response::redirect('/' . $this->context->domain->language . '/login/');
        }

        $user = $this->user();
        $login = $user['login'] ?? 'Гость';

        $wrap = Builder::div('profile-box');
        $wrap->append(Builder::h(1)->content('Профиль'));
        $wrap->append(Builder::p()->content('Вы вошли как: ' . $login));
        $wrap->append(Builder::a('/' . $this->context->domain->language . '/logout/')->content('Выйти'));
        
        return $wrap;
    }
}