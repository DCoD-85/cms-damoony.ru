<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $t = $this->context->service('i18n');
        $text = $t instanceof \Damoony\Core\I18n\Translator ? $t->t('admin.start.placeholder') : 'admin.start.placeholder';

        return Builder::div('admin-start')->content($text);
    }
}
