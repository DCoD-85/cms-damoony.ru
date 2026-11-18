<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Admin\Page\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_projects extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $session = $this->adminSession();
        if ($session === null) {
            return Response::notFound();
        }

        $lang = $this->context->domain->language;
        $wrap = Builder::div('admin-box');
        $wrap->append(Builder::h(1)->content('Проекты'));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content('← Назад'),
        ));

        $site = $this->context->service('site_config');
        if ($site instanceof SiteConfig) {
            $list = Builder::ul('admin-projects');
            foreach ($site->projects() as $key => $project) {
                $list->append(Builder::li()->content(
                    (string) $key
                    . ' · mirrors: ' . implode(', ', $project->hostNames())
                    . ' · aliases: ' . implode(', ', $project->aliasNames())
                    . ' · langs: ' . implode(', ', $project->langs)
                    . ' · default: ' . $project->defaultAlias,
                ));
            }
            $wrap->append($list);
        }

        return $wrap;
    }
}
