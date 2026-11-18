<?php

declare(strict_types=1);

namespace {{namespace}}\persona;

use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Data\Persona\PersonaAuth;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;

final class algoritm_lang_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('persona_auth');
        if ($auth instanceof PersonaAuth) {
            $auth->logout();
        }

        $localAuth = $this->context->service('auth');
        if ($localAuth instanceof \Damoony\Data\Auth\AuthInterface) {
            $localAuth->logout();
        }

        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}
