dbcli — Управление Базами Данных
Инструмент для декларативной работы с БД. Никакого сырого SQL в админке — только JSON-схемы, из которых генерируются SQL-миграции и PHP-провайдеры.

Принцип: CLI-First. Админка и ИИ делегируют работу этому CLI.

1. Основные команды
db:schema:init --domain=[d] --table=[t] — создать JSON-схему таблицы.

db:schema:add-column --domain=[d] --table=[t] --name=[n] --type=[varchar|int|text|timestamp|decimal] [--length=255] [--null=1] — добавить колонку в схему.

db:schema:generate-sql --domain=[d] — сгенерировать SQL-миграцию из app/[d]/.db/schema.json.

db:schema:generate-provider --domain=[d] --table=[t] [--alias=www] — сгенерировать PHP-класс провайдера в app/[d]/[alias]/provider/.

2. Структура JSON-схемы
Файл: app/[domain]/.db/schema.json

json
{
  "tables": {
    "products": {
      "columns": {
        "id": { "type": "int", "primary": true, "auto_increment": true },
        "title": { "type": "varchar", "length": 255 },
        "price": { "type": "decimal" },
        "created_at": { "type": "timestamp", "default": "CURRENT_TIMESTAMP" }
      }
    }
  }
}
3. Полный цикл создания таблицы
bash
# 1. Создать таблицу (базовая структура)
php bin/dbcli.php db:schema:init --domain=mysite.ru --table=products

# 2. Добавить колонки
php bin/dbcli.php db:schema:add-column --domain=mysite.ru --table=products --name=title --type=varchar --length=255
php bin/dbcli.php db:schema:add-column --domain=mysite.ru --table=products --name=price --type=decimal

# 3. Сгенерировать и применить SQL
php bin/dbcli.php db:schema:generate-sql --domain=mysite.ru
php bin/cli.php db:migrate

# 4. Сгенерировать PHP-провайдер
php bin/dbcli.php db:schema:generate-provider --domain=mysite.ru --table=products
После этого можно использовать db:call и db:mutate с этим провайдером.