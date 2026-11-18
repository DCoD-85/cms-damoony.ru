<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

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

        $wrap = Builder::div('admin-server');
        $wrap->append(Builder::h(1)->content('Серверная админка'));
        $wrap->append(Builder::p()->content('Роль: superadmin'));
        $wrap->append(Builder::p()->content('Хостов в конфиге: ' . count($site->projectKeys())));

        $list = Builder::ul('admin-projects');
        foreach ($site->projects() as $key => $project) {
            $item = Builder::li();
            $item->append(Builder::el('strong')->content((string) $key));
            $item->append(Builder::div()->content(
                'mirrors: ' . implode(', ', $project->hostNames())
                . ' · aliases: ' . implode(', ', $project->aliasNames())
                . ' · langs: ' . implode(', ', $project->langs)
                . ' · default: ' . $project->defaultAlias,
            ));
            $list->append($item);
        }
        $wrap->append($list);

        return $wrap;
    }
}
