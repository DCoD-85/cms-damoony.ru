<?php

declare(strict_types=1);

namespace admin_server;

use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\Admin\AdminModule;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class blockslocal_www_left implements RegionInterface
{
    public function name(): string
    {
        return 'left';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;
        $links = ['' => 'Главная'];

        $auth = $context->service('admin_auth');
        if ($auth instanceof AdminAuthService) {
            $session = $auth->authenticate((string) ($context->request->cookies[AdminModule::COOKIE] ?? ''));
            if ($session !== null) {
                if ($session['role'] === AdminAuthService::ROLE_SUPERADMIN) {
                    $links += [
                        'content/' => 'Контент',
                        'blocks/' => 'Блоки',
                        'projects/' => 'Проекты',
                        'accounts/' => 'Аккаунты',
                        'ops/' => 'Мониторинг',
                        'audit/' => 'Аудит',
                    ];
                } elseif ($session['role'] === AdminAuthService::ROLE_DOMAINADMIN) {
                    $links['content/'] = 'Контент';
                    $links['blocks/'] = 'Блоки';
                    $links['domain/'] = 'Мой домен';
                }
                $links['logout/'] = 'Выйти';
            }
        }

        $nav = Builder::ul('admin-nav admin-nav-vertical');
        foreach ($links as $path => $label) {
            $nav->append(Builder::li()->append(
                Builder::a('/' . $lang . '/' . $path)->content($label),
            ));
        }

        return Builder::div('dt_inner')->append($nav);
    }
}
