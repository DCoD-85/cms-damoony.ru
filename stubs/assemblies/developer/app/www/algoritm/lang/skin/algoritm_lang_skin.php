<?php
declare(strict_types=1);
namespace {{namespace}}\www;
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm_lang_skin extends Page
{
    protected function content(): Node|Response|null
    {
        $skin = (string) ($this->args['skin'] ?? 'light');
        setcookie('skin', $skin, time() + 60 * 60 * 24 * 30, '/');
        
        // Берем предыдущую страницу из Referer (без хардкода)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $return = parse_url($referer, PHP_URL_PATH) ?: '/' . $this->context->domain->language . '/';
        
        // Защита: если в referer затесался текущий путь скина, отрезаем его
        $return = preg_replace('#/skin/[a-z]+/?#', '/', $return);

        if ($this->isAjax()) {
            $m = new \Damoony\UI\Response\MinStruct();
            $m->skin($skin, $return);
            return $m->toResponse();
        }
        
        return Response::redirect($return);
    }
}