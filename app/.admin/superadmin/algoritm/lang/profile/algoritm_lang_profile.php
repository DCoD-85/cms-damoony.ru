<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;
use Damoony\Ui\Builder;
use Damoony\Ui\Components\Card;
use Damoony\Ui\Components\Table;
use Damoony\Ui\Components\Thead;
use Damoony\Ui\Components\Tbody;
use Damoony\Ui\Components\Tr;
use Damoony\Ui\Components\Th;
use Damoony\Ui\Components\Td;
use Damoony\Ui\Components\Link;
use Damoony\Ui\Components\Form;
use Damoony\Ui\Components\Input;
use Damoony\Ui\Components\Button;
use Damoony\Ui\Components\Label;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_lang_profile extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $site = $this->context->service('site');

        // Наполняем только центр
        
        $content = new Card($t->t('admin.profile.title'));
        $content->add(Builder::p()->content('Здесь будет профиль администратора.'));
        

        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
