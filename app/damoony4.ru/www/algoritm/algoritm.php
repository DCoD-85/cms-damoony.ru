<?php

declare(strict_types=1);

namespace damoony4_ru\www;

use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? '';
        if ($lang === '') {
            $default = 'ru';
            $site = $this->context->service('site');
            if ($site instanceof \Damoony\Core\Config) {
                $default = (string) $site->getByKey(['domains_langs', $this->context->domain->real, 0], 'ru');
            }
            return Response::redirect('/' . $default . '/');
        }
        return null;
    }
}
