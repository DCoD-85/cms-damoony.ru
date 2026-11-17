# Рефакторинг site.config → domains_app (рабочий план)

Живой чек-лист. Обновляется по ходу; после завершения удаляется.

## Цель

Единственная схема конфига — `domains_app` (`folder` + `namespace` + `mirrors` + алиасы).
`domains_real`, `domains_mirrors`, `domains_mirrors_available`, `fillers` — удаляются полностью,
без слоя совместимости.

## Конвенция имён

- Каноническое имя всегда у **нового**: `bin/cli.php`, `config/site.config`, `src/Cli/Commands`.
- Старое получает постфикс `old`: `config/site.config.old`, `src/Cli/CommandsOld`.
- Удаление старого — только когда проверка **полностью** перенесена на новое.

## Правило переноса тестов

Старый тест удаляется в том же коммите, где его аналог в новом стеке стал зелёным.
Тесты ядра, не зависящие от конфига (`PageCompiler`, `BlockCompiler`, `UrlSanitizer`,
`Fs`, `Paths`, `FileRouter`, `Translator`), не удаляются вообще.

## Критерий готовности (swap-point)

1. Все команды из `bin/cli.old.php` (65) реализованы в новом стеке.
2. `site:config:migrate` переносит `site.config.old` → `site.config` без потерь.
3. Паритет-прогон read-only команд на реальном `app/` совпадает.
4. `phpunit` + `phpstan` + `php-cs-fixer` зелёные.
5. `_doc/*` описывают новую схему.
6. Только после этого: `src/Cli/Commands` → `src/Cli/CommandsOld`, `src/Cli/Next/*` → канонические пути,
   затем удаление `*Old` и `config/site.config.old`.

## Статус

### Готово

- `core-settings`: `SiteConfig`, `ProjectSettings`, `AliasSettings`, `SiteConfigWriter`, `UnknownProjectException`.
- `core-settings`: поля `scheme`, `stub`, `default_alias`, `langs`, `aliases_storage`, `alliases[].stub`.
- `cli-next`: `Quarantine`, `TargetResolver`, `ResolvedTarget`, `PageAddress`, `ContentRefs`, `Scaffolder`.
- Команды: `help`, `domain:list|new|bind|unbind|remove`, `allias:list|new|remove`,
  `page:list|new|read|write|toggle`, `scheme:list|new|copy|save|remove`,
  `block:list|new|write|edit|remove`.
- Инфраструктура: `SessionGuard` (проверка `.session.json` permissions).
- Конфиг переключён: `config/site.config` (новая схема), `config/site.config.old` (артефакт).
- `bin/cli.php` — новый стек; старый вход удалён.

### Осталось (по группам)

| Группа | Команды | Статус |
|---|---|---|
| schemes | `scheme:new`, `scheme:copy`, `scheme:save`, `scheme:remove`, `scheme:list` | готово |
| blocks | `block:new`, `block:write`, `block:edit`, `block:remove`, `block:list` | готово |
| fillers | `filler:new`, `filler:write`, `filler:edit`, `filler:toggle`, `filler:list` | — |
| skins | `skin:new`, `skin:edit`, `skin:remove`, `skin:list` | — |
| langs | `lang:list`, `lang:add`, `lang:remove`, `lang:repair`, `lang:sync` | — |
| sessions | `session:start`, `session:apply`, `session:discard`, `session:list` | — |
| content | `content:store`, `content:remove`, `content:list`, `content:gc` | — |
| structure | `structure:check`, `structure:repair` | — |
| trash/system | `trash:clear`, `system:scan`, `system:clean`, `backup:create`, `backup:restore` | — |
| db | `db:schema`, `db:methods`, `db:describe`, `db:call`, `db:mutate`, `db:migrate`, `dataprovider:add-method` | — |
| admin/user | `admin:passwd`, `admin:new`, `user:new` | — |
| прочее | `api:reflect`, `migrate:page-rules`, `assembly:new`/`new`, `ai:edit-page` | — |
| миграция | `site:config:migrate` | — |

## Решения (журнал)

- `admin` — не проект в `domains_app`; `app/.admin` остаётся инфраструктурой.
- Пустой `domains_app` допустим (свежая установка, удаление последнего проекта).
- Настройки проекта живут в `domains_app[key]`; `fillers` в конфиге не существует.
- URL картинки в JSON-блоке — атрибут `content` (не `url`).
