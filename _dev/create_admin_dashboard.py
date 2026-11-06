from pathlib import Path
import subprocess
import json

# 1. Создаем директории
base = Path("app/.admin/superadmin/algoritm")
base.mkdir(parents=True, exist_ok=True)

# 2. Создаем .rules.json (с флагом terminate)
rules = {
    "auth": True,
    "terminate": True,
    "available": [""]
}
(base / "algoritm.rules.json").write_text(json.dumps(rules, indent=4), encoding="utf-8")

# 3. Создаем PHP-страницу Дашборда
php_content = """<?php

declare(strict_types=1);

namespace Damoony\\Admin\\Pages\\superadmin;

use Damoony\\Algoritm\\Page;
use Damoony\\Http\\Response;
use Damoony\\Ui\\Node;
use Damoony\\Ui\\Builder;
use Damoony\\Ui\\Components\\Card;
use Damoony\\Ui\\Components\\Table;
use Damoony\\Ui\\Components\\Thead;
use Damoony\\Ui\\Components\\Tbody;
use Damoony\\Ui\\Components\\Tr;
use Damoony\\Ui\\Components\\Th;
use Damoony\\Ui\\Components\\Td;
use Damoony\\Ui\\Components\\Link;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $domain = $this->context->domain->domain;
        $alias = $this->context->domain->allias;
        $role = $_SESSION['admin_context']['role'] ?? 'superadmin';

        // Карточка приветствия
        $welcome = new Card('Панель управления');
        $welcome->add(Builder::p()->content("Вы вошли как: {$role}"));
        $welcome->add(Builder::p()->content("Текущий контекст: {$domain} (/{$alias})"));
        
        $actions = Builder::div('admin-actions');
        $actions->append((new Link('/admin/pages', 'Управление страницами'))->attrs()->classAdd('ui-btn'));
        $welcome->add($actions);

        // Таблица доменов (просто для примера)
        $table = new Table();
        $thead = new Thead();
        $tr_head = new Tr();
        $tr_head->add(new Th('Домен'));
        $tr_head->add(new Th('Статус'));
        $thead->add($tr_head);
        
        $tbody = new Tbody();
        $tr_body = new Tr();
        $tr_body->add(new Td($domain));
        $tr_body->add(new Td('Активен'));
        $tbody->add($tr_body);
        
        $table->add($thead);
        $table->add($tbody);

        $content = Builder::div('admin-content');
        $content->append($welcome);
        $content->append($table);

        return $content;
    }
}
"""
php_path = base / "algoritm.php"
php_path.write_text(php_content, encoding="utf-8")

# Проверка синтаксиса
php_bin = "E:/localserver/tools/php8.5.12/php.exe"
ini_path = "E:/localserver/tools/php8.5.12/php.ini"
result = subprocess.run([php_bin, "-c", ini_path, "-l", str(php_path)], capture_output=True, text=True)
if result.returncode != 0:
    print(f"Syntax Error:\n{result.stderr}")
else:
    print("Created + Syntax OK: app/.admin/superadmin/algoritm/algoritm.php")
    print("Created + Syntax OK: app/.admin/superadmin/algoritm/algoritm.rules.json")