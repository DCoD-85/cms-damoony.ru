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
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Label;
use Damoony\Core\Files\Paths;
use Damoony\Core\Config;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class algoritm_lang_domain extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $domainName = $this->args['id'] ?? $this->args['domain'] ?? '';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $site = $this->context->service('site');
        $paths = $this->context->service('paths');

        if ($domainName === '') {
            return Response::redirect("/admin/{$lang}/domains");
        }

        // Проверка: существует ли домен в конфиге
        $domainsMap = $site instanceof Config ? $site->getByKey(['domains_real'], []) : [];
        $realDomains = is_array($domainsMap) ? array_values($domainsMap) : [];
        
        if (!in_array($domainName, $realDomains, true)) {
            $errorCard = new Card('Ошибка 404');
            $errorCard->add(Builder::div('error-msg')->content("Домен '{$domainName}' не найден в конфигурации."));
            
            $backBtn = new Link("/admin/{$lang}/domains", 'Назад к списку');
            $backBtn->attrs()->classAdd('ui-btn');
            $errorCard->add($backBtn);
            
            $this->setContent('center', $errorCard);
            
            $loader = new SchemeLoader($paths, null, $role);
            $manifest = $loader->load($this->context->domain, 'admin');
            return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
        }

        $error = '';
        $success = '';

        // Обработка добавления алиаса
        if ($this->context->request->isPost()) {
            $aliasName = trim((string) $this->context->request->post('alias', ''));
            
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $aliasName)) {
                $error = 'Некорректное имя алиаса (только a-z, 0-9, _)';
            } else {
                // Вызов CLI: allias:new --domain=... --name=...
                $phpBinary = PHP_BINARY;
                $cliPath = $paths instanceof Paths ? $paths->root() . '/bin/cli.php' : 'bin/cli.php';
                $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($cliPath) . ' allias:new --domain=' . escapeshellarg($domainName) . ' --name=' . escapeshellarg($aliasName) . ' 2>&1';
                
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    return Response::redirect("/admin/{$lang}/domain/{$domainName}");
                } else {
                    $error = 'Ошибка: ' . implode('\n', $output);
                }
            }
        }

        $content = Builder::div('');

        // Заголовок домена и кнопка назад
        $header = Builder::div('admin-actions');
        $header->append(Builder::h(2)->content("Настройки домена: {$domainName}"));
        $backBtn = new Link("/admin/{$lang}/domains", 'Назад к списку');
        $backBtn->attrs()->classAdd('ui-btn')->classAdd('ui-btn-sm');
        $header->append($backBtn);
        $content->append($header);

        if ($error !== '') {
            $content->append(Builder::div('error-msg')->content($error));
        }

        // Таблица алиасов
        $aliasesMap = [];
        $aliasesAvailMap = [];
        if ($site instanceof Config) {
            $map = $site->getByKey(['alliases_real', $domainName], []);
            if (is_array($map)) $aliasesMap = $map;
            
            $avail = $site->getByKey(['alliases_available', $domainName], []);
            if (is_array($avail)) $aliasesAvailMap = $avail;
        }

        $table = new Table();
        $thead = new Thead();
        $trHead = new Tr();
        $trHead->add(new Th('Алиас'));
        $trHead->add(new Th('Статус'));
        $trHead->add(new Th('Действия'));
        $thead->add($trHead);

        $tbody = new Tbody();
        if (empty($aliasesMap)) {
            $tr = new Tr();
            $td = new Td();
            $td->attrs()->add('colspan', '3');
            $td->content('Алиасы не найдены.');
            $tr->add($td);
            $tbody->add($tr);
        } else {
            foreach ($aliasesMap as $aliasKey => $realName) {
                if ($aliasKey === '') continue; // Пропускаем пустой ключ
                $isActive = $aliasesAvailMap[$aliasKey] ?? false;
                
                $tr = new Tr();
                $tr->add((new Td())->content($aliasKey));
                
                $toggle = new Toggle('alias_active', $isActive);
                $input = $toggle->children()[0];
                if ($input instanceof \Damoony\UI\Element) {
                    $input->attrs()->add('data-domain', $domainName);
                    $input->attrs()->add('data-alias', $aliasKey);
                    $input->attrs()->add('data-lang', $lang);
                }
                $tr->add((new Td())->add($toggle));
                
                $tr->add((new Td())->content('—')); // В будущем сюда добавим кнопку "Редактировать"
                $tbody->add($tr);
            }
        }
        $table->add($thead);
        $table->add($tbody);
        $content->append($table);

        // Форма добавления нового алиаса
        $addCard = new Card('Добавить алиас');
        $form = new Form();
        $form->action("/admin/{$lang}/domain/{$domainName}")->method('POST');
        
        $form->add(new Label('Имя алиаса (например: www, shop, blog)'));
        $form->add(new Input('text', 'alias', ''));
        $form->add(new Button('Создать алиас'));
        
        $addCard->add($form);
        $content->append($addCard);

        $this->setContent('center', $content);

        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
