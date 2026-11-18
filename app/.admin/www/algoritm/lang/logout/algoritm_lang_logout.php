<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Core\Http\Response;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\Admin\AdminModule;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

final class algoritm_lang_logout extends Page
{
    protected function content(): Node|Response|null
    {
        $auth = $this->context->service('admin_auth');
        if ($auth instanceof AdminAuthService) {
            $auth->logout((string) ($this->context->request->cookies[AdminModule::COOKIE] ?? ''));
        }

        setcookie(AdminModule::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return Response::redirect('/' . $this->context->domain->language . '/');
    }
}
