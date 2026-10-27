<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Response\AjaxStruct;
use Damoony\Response\MinStruct;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

class AjaxPage extends Algoritm
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
