<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;

        $wrap = Builder::div('dt_inner');

        $menu = Builder::ul('dt_menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('menu.home'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/about/')->content($context->lang('menu.about'))));

        $auth = $context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated()) {
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/profile/')->content($context->lang('menu.profile'))));
        } else {
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/login/')->content($context->lang('menu.login'))));
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/registration/')->content($context->lang('menu.register'))));
        }

        $wrap->append($menu);

        return $wrap;
    }
}