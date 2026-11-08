<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Core\Config;

final class alliasglobal_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $container = Builder::div('admin-left-inner');
        $path = $context->request->path;
        
        // Логика: если мы в разделе /domains, показываем список доменов
        if (str_contains($path, '/domains')) {
            $site = $context->service('site');
            $lang = $context->service('i18n')->current();
            
            $container->append(Builder::div('sidebar-title')->content('Ваши домены'));
            
            $domains = [];
            if ($site instanceof Config) {
                $domainsMap = $site->getByKey(['domains_real'], []);
                if (is_array($domainsMap)) $domains = array_unique(array_values($domainsMap));
            }

            $list = Builder::ul('sidebar-list');
            foreach ($domains as $domain) {
                if ($domain === '') continue;
                
                // Подсвечиваем активный домен
                $isActive = str_contains($path, "/{$domain}");
                $class = $isActive ? 'active' : '';
                
                $list->append(Builder::li()->append(
                    Builder::a("/admin/{$lang}/domains/{$domain}", $class)->content($domain)
                ));
            }
            $container->append($list);

            $addBtn = Builder::a("/admin/{$lang}/domains/create", 'sidebar-btn')->content('+ Добавить домен');
            $container->append($addBtn);
        }

        return $container;
    }
}
