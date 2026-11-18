<?php

declare(strict_types=1);

namespace {{namespace}}\developer;

use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Data\Persona\PersonaAuth;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        return Response::redirect('/' . $this->context->domain->language . '/', 301);    }
}
