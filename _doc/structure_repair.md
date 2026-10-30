--- НАЧАЛО ДОКУМЕНТА ---

Команда structure:repair — Умное восстановление структуры
Назначение: автоматически чинить «битые» места в файловой структуре домена: восстанавливать удаленные алиасы, синхронизировать JSON-манифесты (`schemes.json`, `blocks.json`) с реальными файлами на диске.

Статус: реализовано (stage-6). Покрыто тестами: _dev/tests/Unit/Cli/StructureRepairCommandTest.php (7 тестов). PHPStan level 8 проходит.

Зачем это нужно
При ручном удалении файлов (например, `rm -rf app/example.ru/www/`) или при сбое деплоя структура может разойтись:
- В `config/site.config` есть алиас `www`, а папки `app/example.ru/www/algoritm/` нет.
- На диске есть файл `www.data.php`, но в `schemes.json` он не записан («сирота»).
- В `blocks.json` нет записи о блоке, папка которого существует.

Команда `structure:repair` восстанавливает эти связи, не удаляя данные.

Что чинит (и что — нет)
✅ Восстанавливает:
- Алиасы: если в конфиге есть алиас, но нет папки — создаёт `algoritm/`, `.rules.json` и `algoritm.php`.
- Схемы: если на диске есть `*.data.php`, но его нет в `schemes.json` — добавляет запись.
- Блоки: если на диске есть папка `.alliasglobal/<name>/`, но его нет в `blocks.json` — добавляет запись с типом `mixed`.
- Корректно обрабатывает пустые алиасы (`""` → `www`) — **не создаёт лишних папок**.

❌ НЕ восстанавливает:
- Вложенные страницы (`page.editor.json` и `algoritm_*.php` внутри алиасов) — для них используйте `page:new` или `page:save`.
- Битые `.editor.json` (поврежденный JSON) — их нужно пересоздать вручную или через админку.
- Настройки в `site.config` (домены, скины, fillers) — только проверяет их наличие.

Синтаксис
bash
php bin/cli.php structure:repair --domain=example.ru
--domain — FQDN реального домена (обязательно).

Пример работы
bash
$ php bin/cli.php structure:repair --domain=damoony.ru
✓ Восстановлен алиас: www
✓ Восстановлена запись схемы в schemes.json: visuo
✓ Восстановлена запись блока в blocks.json: footer
✓ Ремонт завершен. Исправлено элементов: 3
Если структура в порядке:
bash
$ php bin/cli.php structure:repair --domain=damoony.ru
✓ Структура в порядке, исправлений не требуется.

Что делать при ошибках
Если команда падает:
1. Проверьте `config/site.config` — валидный ли JSON, есть ли `domains_real` с self-mapping.
2. Проверьте права на запись в папке `app/` (владелец должен быть `www-data`).
3. Запустите `structure:check` для диагностики.

Отличие от structure:check
- `structure:check` — **только показывает** ошибки (read-only).
- `structure:repair` — **исправляет** их (write).

Поэтому: сначала `structure:check`, потом `structure:repair`, потом снова `structure:check` (чтобы убедиться, что всё зелёное).

Где находится код
- `src/Cli/Commands/StructureRepairCommand.php`
- Тесты: `_dev/tests/Unit/Cli/StructureRepairCommandTest.php`

Ссылки
- cli.md — краткая справка по команде.
- manifest.md §7 — P0/P1/P2 статусы.
- contracts.md — контракты.

--- КОНЕЦ ДОКУМЕНТА ---