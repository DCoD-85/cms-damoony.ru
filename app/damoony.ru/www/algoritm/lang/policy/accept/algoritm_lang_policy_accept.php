<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Img;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Textarea;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Td;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;
use Damoony\Core\Http\Response;

final class algoritm_lang_policy_accept extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        // GET → 404: страница только для POST-обработки формы.
        if (!$this->isPost()) {
            return Response::notFound();
        }

        // CSRF уже проверен глобально в PageHandler для всех POST.

        if (!headers_sent()) {
            setcookie('damoony_policy', 'accepted', [
                'expires' => time() + 86400 * 365,
                'path' => '/',
                'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
                'httponly' => false,  // JS читает через document.cookie
                'samesite' => 'Lax',
            ]);
        }

        // Open-redirect guard: из Referer берём только path (CWE-601).
        $lang = $this->context->domain->language;
        $target = '/' . $lang . '/policy/';

        $referer = $this->context->request->header('Referer') ?? '';
        if ($referer !== '') {
            $path = parse_url($referer, PHP_URL_PATH);
            if (is_string($path) && $path !== '' && str_starts_with($path, '/')) {
                $target = $path;
            }
        }

        return Response::redirect($target, 302);
    }
}
