<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Response\AjaxStruct;
use Damoony\UI\Response\MinStruct;

class AjaxPage extends Page
{
    public function default(): Node|Response|null
    {
        if ($this->isAjax()) {
            return $this->jsonResponse();
        }

        return Builder::div('ajax-demo')->content('Non-ajax view');
    }

    private function jsonResponse(): Response
    {
        $mode = $this->args['mode'] ?? 'min';

        if ($mode === 'layout') {
            $struct = new AjaxStruct();
            $struct->address($this->context->request->path);
            $struct->header('<title>ajax</title>');
            $struct->center(Builder::div('center')->content('ajax center')->release());
            return $struct->toResponse();
        }

        $min = new MinStruct();
        $min->error(false);
        $min->messages('<div class="msg">ok</div>');
        $min->release(Builder::div('min-release')->content('ajax min'));
        $min->form(false, '/next', 2);
        return $min->toResponse();
    }
}
