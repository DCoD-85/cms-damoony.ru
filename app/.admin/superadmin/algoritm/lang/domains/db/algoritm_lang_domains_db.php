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
use Damoony\Db\DataProvider\DynamicProvider;
use Damoony\Db\Connection;
use Damoony\Files\Paths;
use Damoony\Core\Config;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_lang_domains_db extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $domainName = $this->args['domain'] ?? '';
        $tableName = $this->args['table'] ?? '';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $paths = $this->context->service('paths');
        $site = $this->context->service('site');
        $db = $this->context->service('db');

        $content = Builder::div('');

        if (!$db instanceof Connection || !$paths instanceof Paths) {
            return Response::internalError('DB or Paths missing');
        }

        if ($tableName === '') {
            // Показываем список таблиц
            $content->append(Builder::h(2)->content("База данных домена: {$domainName}"));
            
            $tables = [];
            try {
                $stmt = $db->pdo()->query('SHOW TABLES');
                if ($stmt !== false) {
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                }
            } catch (\Throwable $e) {}

            $list = Builder::ul('db-tables-list');
            foreach ($tables as $table) {
                $list->append(Builder::li()->append(
                    Builder::a("/admin/{$lang}/domains/{$domainName}/db/{$table}")->content($table)
                ));
            }
            $content->append($list);
        } else {
            // Показываем содержимое конкретной таблицы
            $content->append(Builder::div('admin-actions')->append(
                Builder::h(2)->content("Таблица: {$tableName}"),
                new Link("/admin/{$lang}/domains/{$domainName}/db", 'Назад к таблицам')
            ));

            // Получаем колонки
            $writableCols = [];
            $stmt = $db->pdo()->query("DESCRIBE `{$tableName}`");
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $writableCols[] = $row['Field'];
                }
            }

            $provider = new DynamicProvider($db, $site, $domainName, 'www', $tableName, $writableCols);
            
            try {
                $rows = $provider->get([], 50);
            } catch (\Throwable $e) {
                $rows = [];
                $content->append(Builder::div('error-msg')->content('Ошибка чтения: ' . $e->getMessage()));
            }

            $table = new Table();
            $thead = new Thead();
            $trHead = new Tr();
            
            // Выводим заголовки колонок
            if (!empty($rows)) {
                foreach (array_keys($rows[0]) as $col) {
                    $trHead->add(new Th($col));
                }
            }
            $thead->add($trHead);

            $tbody = new Tbody();
            foreach ($rows as $row) {
                $tr = new Tr();
                foreach ($row as $val) {
                    $tr->add((new Td())->content((string) $val));
                }
                $tbody->add($tr);
            }
            
            $table->add($thead);
            $table->add($tbody);
            $content->append($table);
        }

        $this->setContent('center', $content);

        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
