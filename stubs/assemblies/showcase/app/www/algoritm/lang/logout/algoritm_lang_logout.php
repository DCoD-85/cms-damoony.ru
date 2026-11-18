<?php
declare(strict_types=1);
namespace {{namespace}}\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Node;

final class algoritm_lang_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface) {
            $auth->logout();
        }

        $personaAuth = $this->context->service('persona_auth');
        if ($personaAuth instanceof \Damoony\Data\Persona\PersonaAuth) {
            $personaAuth->logout();
        }
        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}