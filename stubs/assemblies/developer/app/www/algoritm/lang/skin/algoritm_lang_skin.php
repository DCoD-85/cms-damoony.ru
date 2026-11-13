<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Node;

final class algoritm_lang_skin extends Page
{
    protected function content(): Node|Response|null
    {
        $skinName = (string) ($this->args['skin'] ?? '');
        $site = $this->context->service('site');
        $real = $this->context->domain->real;
        $allias = $this->context->domain->allias;
        $lang = $this->context->domain->language;
        
        $allowed = [];
        if ($site instanceof \Damoony\Core\Config) {
            $allowed = (array) $site->getByKey(['alliases_skins_allowed', $real, $allias], []);
        }
        
        // Валидация: если скин недоступен — редирект с ошибкой (или 404)
        if ($skinName === '' || !in_array($skinName, $allowed, true)) {
            return Response::redirect('/' . $lang . '/');
        }
        
        // Ставим куку
        setcookie('skin', $skinName, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        
        // Возврат туда, откуда пришли (или на главную)
        $referer = $_SERVER['HTTP_REFERER'] ?? ('/' . $lang . '/');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = str_contains($referer, $host);
        $path = $isLocal ? (parse_url($referer, PHP_URL_PATH) ?: '/') : ('/' . $lang . '/');
        
        // Если это AJAX (cliajax), просим JS перезагрузить страницу
        if ($this->isAjax()) {
            return Response::json(['ok' => true, 'reload' => true]);
        }
        
        return Response::redirect($path, 302);
    }
}