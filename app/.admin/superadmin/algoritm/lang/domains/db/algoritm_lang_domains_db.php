<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
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
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;

final class algoritm_lang_domains_db extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $domainName = $this->args['domain'] ?? '';
        $tableName = $this->args['table'] ?? '';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $paths = $this->context->service('paths');
        $db = $this->context->service('db');

        if (!$db instanceof Connection || !$paths instanceof Paths) {
            return Response::internalError('DB or Paths missing');
        }

        $root = $paths->root();
        $phpBin = PHP_BINARY;
        if (str_ends_with($phpBin, 'php-cgi.exe')) {
            $phpBin = str_replace('php-cgi.exe', 'php.exe', $phpBin);
        }

        $content = Builder::div('');

        if ($this->context->request->isPost()) {
            if ($tableName === '') {
                $newTable = trim((string) $this->context->request->post('table_name', ''));
                if (preg_match('/^[a-z][a-z0-9_]*$/', $newTable)) {
                    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/bin/dbcli.php') . ' db:schema:init --domain=' . escapeshellarg($domainName) . ' --table=' . escapeshellarg($newTable) . ' 2>&1';
                    exec($cmd, $out1, $ret1);
                    
                    if ($ret1 === 0) {
                        $this->runGenSqlAndProvider($phpBin, $root, $domainName, $newTable);
                        $_SESSION['flash'][] = ['type' => 'success', 'message' => 'Таблица создана: ' . $newTable];
                        return Response::redirect("/admin/{$lang}/domains/{$domainName}/db/{$newTable}");
                    }
                }
            } else {
                $colName = trim((string) $this->context->request->post('col_name', ''));
                $colType = trim((string) $this->context->request->post('col_type', 'varchar'));
                $colLength = trim((string) $this->context->request->post('col_length', ''));
                $isNull = $this->context->request->post('is_null') === '1';

                if (preg_match('/^[a-z][a-z0-9_]*$/', $colName)) {
                    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/bin/dbcli.php') . ' db:schema:add-column --domain=' . escapeshellarg($domainName) . ' --table=' . escapeshellarg($tableName) . ' --name=' . escapeshellarg($colName) . ' --type=' . escapeshellarg($colType);
                    if ($colLength !== '') $cmd .= ' --length=' . escapeshellarg($colLength);
                    if ($isNull) $cmd .= ' --null=1';
                    $cmd .= ' 2>&1';
                    
                    exec($cmd, $out2, $ret2);
                    
                    if ($ret2 === 0) {
                        $this->runGenSqlAndProvider($phpBin, $root, $domainName, $tableName);
                        $_SESSION['flash'][] = ['type' => 'success', 'message' => 'Колонка добавлена: ' . $colName];
                        return Response::redirect("/admin/{$lang}/domains/{$domainName}/db/{$tableName}");
                    }
                }
            }
        }

        if ($tableName === '') {
            $content->append(Builder::h(2)->content("База данных домена: {$domainName}"));
            
            $tables = [];
            try {
                $stmt = $db->pdo()->query('SHOW TABLES');
                if ($stmt !== false) $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Throwable $e) {}

            $list = Builder::ul('db-tables-list');
            foreach ($tables as $table) {
                $list->append(Builder::li()->append(
                    Builder::a("/admin/{$lang}/domains/{$domainName}/db/{$table}")->content($table)
                ));
            }
            $content->append($list);

            $card = new Card('Создать новую таблицу');
            $form = new Form();
            $form->action("/admin/{$lang}/domains/{$domainName}/db")->method('POST');
            $form->add(new Label('Имя таблицы (a-z, 0-9, _)'));
            $form->add(new Input('text', 'table_name', ''));
            $form->add(new Button('Создать'));
            $card->add($form);
            $content->append($card);

        } else {
            $content->append(Builder::div('admin-actions')->append(
                Builder::h(2)->content("Таблица: {$tableName}"),
                new Link("/admin/{$lang}/domains/{$domainName}/db", 'Назад к таблицам')
            ));

            try {
                $stmt = $db->pdo()->query("SELECT * FROM `{$tableName}` LIMIT 50");
                $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            } catch (\Throwable $e) {
                $rows = [];
                $content->append(Builder::div('error-msg')->content('Ошибка: ' . $e->getMessage()));
            }

            $table = new Table();
            $thead = new Thead();
            $trHead = new Tr();
            if (!empty($rows)) {
                foreach (array_keys($rows[0]) as $col) $trHead->add(new Th($col));
            }
            $thead->add($trHead);

            $tbody = new Tbody();
            foreach ($rows as $row) {
                $tr = new Tr();
                foreach ($row as $val) $tr->add((new Td())->content((string) $val));
                $tbody->add($tr);
            }
            $table->add($thead);
            $table->add($tbody);
            $content->append($table);

            $card = new Card('Добавить колонку');
            $form = new Form();
            $form->action("/admin/{$lang}/domains/{$domainName}/db/{$tableName}")->method('POST');
            
            $form->add(new Label('Имя колонки (a-z, 0-9, _)'));
            $form->add(new Input('text', 'col_name', ''));
            
            $form->add(new Label('Тип'));
            $select = new Select('col_type');
            $select->add(new Option('varchar', 'VARCHAR'));
            $select->add(new Option('int', 'INT'));
            $select->add(new Option('text', 'TEXT'));
            $select->add(new Option('decimal', 'DECIMAL'));
            $select->add(new Option('timestamp', 'TIMESTAMP'));
            $form->add($select);

            $form->add(new Label('Длина (для VARCHAR, опционально)'));
            $form->add(new Input('text', 'col_length', ''));

            $form->add(new Label('Разрешить NULL?'));
            $form->add(new Input('checkbox', 'is_null', '1'));

            $form->add(new Button('Добавить колонку'));
            $card->add($form);
            $content->append($card);
        }

        $this->setContent('center', $content);

        return $content;
    }

    private function runGenSqlAndProvider(string $phpBin, string $root, string $domain, string $table): void
    {
        exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/bin/dbcli.php') . ' db:schema:generate-sql --domain=' . escapeshellarg($domain) . ' 2>&1');
        exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/bin/cli.php') . ' db:migrate 2>&1');
        exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/bin/dbcli.php') . ' db:schema:generate-provider --domain=' . escapeshellarg($domain) . ' --table=' . escapeshellarg($table) . ' 2>&1');
    }
}
