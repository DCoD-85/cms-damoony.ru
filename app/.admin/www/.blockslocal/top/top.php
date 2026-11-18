<?php

declare(strict_types=1);

namespace admin_server;

use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\Admin\AdminModule;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class blockslocal_www_top implements RegionInterface
{
    public function name(): string
    {
        return 'top';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('admin-topbar');
        $wrap->append(Builder::div('admin-brand')->content('Админка сервера'));

        $auth = $context->service('admin_auth');
        if ($auth instanceof AdminAuthService) {
            $session = $auth->authenticate((string) ($context->request->cookies[AdminModule::COOKIE] ?? ''));
            if ($session !== null) {
                $wrap->append(Builder::div('admin-user')->content(
                    (string) $session['account']['login'] . ' · ' . (string) $session['role'],
                ));
                $wrap->append(Builder::div('admin-top-actions')->append(
                    Builder::a('/' . $context->domain->language . '/logout/')->content('Выйти'),
                ));
            }
        }

        return $wrap;
    }
}
