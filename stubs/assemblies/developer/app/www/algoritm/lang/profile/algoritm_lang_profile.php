<?php
declare(strict_types=1);
namespace {{namespace}}\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_profile extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if (!($auth instanceof \Damoony\Data\Auth\AuthInterface) || !$auth->isAuthenticated()) {
            return Response::redirect('/' . $this->context->domain->language . '/login/');
        }
        $user = $auth->user();
        $wrap = Builder::div('profile-box');
        $wrap->append(Builder::h(1)->content('Профиль'));
        $wrap->append(Builder::p()->content('Вы вошли как: ' . ($user['login'] ?? 'Гость')));
        $wrap->append(Builder::a('/' . $this->context->domain->language . '/logout/')->content('Выйти'));
        return $wrap;
    }
}