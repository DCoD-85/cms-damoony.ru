<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Http\Request;
use Damoony\Http\Response;

final class LogoutController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->auth->logout();
        return $this->redirect('/admin/?p=login');
    }
}
