<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
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
        $login = $user['login'] ?? $this->context->lang('profile.guest');

        $wrap = Builder::div('profile-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('profile.title')));
        $wrap->append(Builder::p()->content($this->context->lang('profile.logged_as', ['login' => $login])));
        $wrap->append(Builder::a('/' . $this->context->domain->language . '/logout/')->content($this->context->lang('profile.logout')));
        
        return $wrap;
    }
}