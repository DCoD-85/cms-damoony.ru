<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm_lang_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface) {
            $auth->logout();
        }
        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}