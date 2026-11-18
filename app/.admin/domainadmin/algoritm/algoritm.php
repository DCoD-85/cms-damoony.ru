<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\domainadmin;

use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        $site = $this->context->service('site_config');
        if (!$site instanceof SiteConfig) {
            return Response::notFound();
        }

        $key = $this->context->domain->real;
        $project = $site->project($key);
        if ($project === null) {
            return Response::notFound();
        }

        $wrap = Builder::div('admin-server');
        $wrap->append(Builder::h(1)->content('Админка домена'));
        $wrap->append(Builder::p()->content('Роль: domainadmin'));
        $wrap->append(Builder::p()->content('Проект: ' . $key));
        $wrap->append(Builder::p()->content('Mirrors: ' . implode(', ', $project->hostNames())));

        $list = Builder::ul('admin-projects');
        foreach ($project->aliasNames() as $alias) {
            $list->append(Builder::li()->content(
                $alias . ' — ' . implode(', ', $project->aliasLangs($alias))
                . ($project->isAliasAvailable($alias) ? '' : ' (выключен)'),
            ));
        }
        $wrap->append($list);

        return $wrap;
    }
}
