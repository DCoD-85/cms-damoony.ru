<?php
declare(strict_types=1);
namespace damoony_ru\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm_lang_skin extends Page
{
    protected function content(): Node|Response|null
    {
        $skin = (string) ($this->args['skin'] ?? 'light');
        setcookie('skin', $skin, time() + 60 * 60 * 24 * 30, '/');
        
        if ($this->isAjax()) {
            $m = new \Damoony\UI\Response\MinStruct();
            // Передаем имя скина и URL, куда вернуться (на текущий язык)
            $m->skin($skin, '/' . $this->context->domain->language . '/');
            return $m->toResponse();
        }
        
        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}