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
        // Берем первый аргумент из регулярки ^([a-z]+)$
        $skin = (string) ($this->args[0] ?? $this->args['skin'] ?? 'light');
        
        setcookie('skin', $skin, time() + 60 * 60 * 24 * 30, '/');
        
        // Возвращаем на главную языка
        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}