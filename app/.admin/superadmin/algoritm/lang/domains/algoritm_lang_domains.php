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
use Damoony\UI\Components\Toggle;
use Damoony\Core\Files\Paths;
use Damoony\Core\Config;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class algoritm_lang_domains extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $domainName = $this->args['domain'] ?? ''; // Сюда попадет переменная!
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $site = $this->context->service('site');
        $paths = $this->context->service('paths');

        $content = Builder::div('');

        if ($domainName === '') {
            // === ЛОГИКА СПИСКА ДОМЕНОВ ===
            $header = Builder::div('admin-actions');
            $header->append(Builder::h(2)->content($t->t('admin.tab.domains')));
            $btn = new Link("/admin/{$lang}/domains/create", 'Добавить домен');
            $btn->attrs()->classAdd('ui-btn');
            $header->append($btn);
            $content->append($header);

            $domains = [];
            if ($site instanceof Config) {
                $domainsMap = $site->getByKey(['domains_real'], []);
                if (is_array($domainsMap)) $domains = array_unique(array_values($domainsMap));
            }

            $table = new Table();
            $thead = new Thead();
            $trHead = new Tr();
            $trHead->add(new Th('Домен (Real)'));
            $trHead->add(new Th('Статус'));
            $trHead->add(new Th('Действия'));
            $thead->add($trHead);

            $tbody = new Tbody();
            if (empty($domains)) {
                $tr = new Tr();
                $td = new Td();
                $td->attrs()->add('colspan', '3');
                $td->content('Домены не найдены.');
                $tr->add($td);
                $tbody->add($tr);
            } else {
                foreach ($domains as $domain) {
                    if ($domain === '') continue;
                    $isActive = false;
                    if ($site instanceof Config) {
                        $availMap = $site->getByKey(['domains_available'], []);
                        $isActive = is_array($availMap) ? (bool)($availMap[$domain] ?? false) : false;
                    }

                    $tr = new Tr();
                    $tr->add((new Td())->content($domain));

                    $toggle = new Toggle('domain_active', $isActive);
                    $input = $toggle->children()[0];
                    if ($input instanceof \Damoony\UI\Element) {
                        $input->attrs()->add('data-domain', $domain);
                        $input->attrs()->add('data-lang', $lang);
                    }
                    $tr->add((new Td())->add($toggle));

                    $actions = Builder::div('action-buttons');
                    $editBtn = new Link("/admin/{$lang}/domains/{$domain}", 'Настроить');
                    $editBtn->attrs()->classAdd('ui-btn')->classAdd('ui-btn-sm');
                    $actions->append($editBtn);

                    $tr->add((new Td())->add($actions));
                    $tbody->add($tr);
                }
            }
            $table->add($thead);
            $table->add($tbody);
            $content->append($table);
        } else {
            // === ЛОГИКА НАСТРОЕК ДОМЕНА (ВЛОЖЕННАЯ) ===
            
            // Проверяем, существует ли такой домен
            $domainsMap = $site instanceof Config ? $site->getByKey(['domains_real'], []) : [];
            $realDomains = is_array($domainsMap) ? array_values($domainsMap) : [];
            
            if (!in_array($domainName, $realDomains, true)) {
                $errorCard = new Card('Ошибка 404');
                $errorCard->add(Builder::div('error-msg')->content("Домен '{$domainName}' не найден в конфигурации."));
                $backBtn = new Link("/admin/{$lang}/domains", 'Назад к списку');
                $backBtn->attrs()->classAdd('ui-btn');
                $errorCard->add($backBtn);
                $content->append($errorCard);
            } else {
                $header = Builder::div('admin-actions');
                $header->append(Builder::h(2)->content("Домен: {$domainName}"));
                $backBtn = new Link("/admin/{$lang}/domains", 'Назад к списку');
                $backBtn->attrs()->classAdd('ui-btn')->classAdd('ui-btn-sm');
                $header->append($backBtn);
                $content->append($header);

                // Табы для вложенных страниц
                $tabs = Builder::ul('domain-tabs');
                $tabs->append(Builder::li()->append(Builder::a("/admin/{$lang}/domains/{$domainName}/", 'active')->content('Обзор')));
                $tabs->append(Builder::li()->append(Builder::a("/admin/{$lang}/domains/{$domainName}/schemes", '')->content('Схемы')));
                $tabs->append(Builder::li()->append(Builder::a("/admin/{$lang}/domains/{$domainName}/blocks", '')->content('Блоки')));
                $content->append($tabs);

                $card = new Card('Обзор домена');
                $card->add(Builder::p()->content('Здесь будут алиасы и настройки.'));
                $content->append($card);
            }
        }

        $this->setContent('center', $content);

        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
