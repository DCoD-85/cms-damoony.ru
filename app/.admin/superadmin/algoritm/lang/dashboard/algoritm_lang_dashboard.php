<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Builder;
use Damoony\UI\Components\Card;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Td;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Label;
use Damoony\Core\Files\Paths;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class algoritm_lang_dashboard extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $this->regions()->disable('left'); // Отключаем сайдбар на дашборде
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $site = $this->context->service('site');

        // Наполняем только центр
        
        $content = new Card($t->t('admin.dash.title'));
        $content->add(Builder::p()->content($t->t('admin.dash.logged_in', ['role' => $role])));
        $content->add(Builder::p()->content($t->t('admin.dash.current_lang', ['lang' => $lang])));
        

        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
