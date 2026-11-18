<?php

declare(strict_types=1);

namespace admin_server;

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
        $nav = Builder::ul('admin-nav admin-nav-vertical');
        foreach (['' => 'Главная', 'projects/' => 'Проекты', 'accounts/' => 'Аккаунты', 'ops/' => 'Мониторинг'] as $path => $label) {
            $nav->append(Builder::li()->append(
                Builder::a('/' . $lang . '/' . $path)->content($label),
            ));
        }
        $nav->append(Builder::li()->append(
            Builder::a('/' . $lang . '/logout/')->content('Выйти'),
        ));

        return Builder::div('dt_inner')->append($nav);
    }
}
