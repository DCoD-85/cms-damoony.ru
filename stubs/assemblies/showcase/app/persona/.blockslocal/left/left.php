<?php

declare(strict_types=1);

namespace {{namespace}};

use Damoony\Data\Persona\PersonaAuth;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_persona_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;
        $auth = $context->service('persona_auth');
        $user = $auth instanceof PersonaAuth ? $auth->user() : null;

        $menu = Builder::ul('persona-menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('persona.menu_home'))));

        if ($user === null) {
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/request/')->content($context->lang('persona.menu_request'))));
        } else {
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/profile/')->content($context->lang('persona.menu_profile'))));
            if ($auth instanceof PersonaAuth && $auth->isAdmin((int) $user['id'], 'persona')) {
                $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/admin/')->content($context->lang('persona.menu_admin'))));
            }
            $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/logout/')->content($context->lang('persona.menu_logout'))));
        }

        $wrap = Builder::div('dt_inner');
        $wrap->append($menu);

        return $wrap;
    }
}
