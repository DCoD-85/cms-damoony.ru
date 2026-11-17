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

## Фазы

1. **CLI** (текущая) — все команды CLI на `domains_app`, старый CLI-код удалён.
   Сайт/админка локально могут не работать.
2. **Движок** — `ConfigDomainResolver`, `PageNamespace`, `ContentResponder`, `AppFactory`,
   `StructureChecker` на `SiteConfig`; удаление старых чтений конфига.
3. **Фиксация** — гейты + фиксация рабочего состояния.
4. **Живой тест локально.**
5. **Боевой** — миграция `site.config` на проде, деплой, проверка.

## Backlog фазы 2 (готовим сейчас, не рефакторим)

Точки, которые остаются на старом формате до фазы 2. В фазе 1 их **не портируем**
целиком — только явный fail вместо тихой деградации:

| Файл | Что читает | Статус в фазе 1 |
|---|---|---|
| `src/Cli/Db/Commands/SchemaGenerateProviderCommand.php:70` | `domains_real` → namespace провайдера | fail-fast при схеме `domains_app` |
| `src/Cli/Ai/Handler/AiStartHandler.php:43,48` | перебор `domains_real` | fail-fast при схеме `domains_app` |
| `src/Diagnostics/StructureChecker.php:22,39` | `domains_real`, `domains_scheme` | `structure:*` переносятся в фазу 2 |
| `src/Core/Domain/ConfigDomainResolver.php:28..114` | `domains_mirrors`, `domains_real`, `domains_stub`, `domains_scheme`, `alliases_real` | фаза 2 |
| `src/Core/Content/ContentResponder.php:75` | `domains_real` | фаза 2 |
| `src/UI/Page/PageNamespace.php` | `domains_real` | удаляется в фазе 2 |
| `src/Core/App/AppFactory.php:32` | загрузка `site.config` | фаза 2 |
| `Cli\Next\Support\ProviderInvoker::providerConfig()` | собирает `Core\Config` c `domains_aliases_storage` для конструктора `AbstractProvider` | фаза 2: провайдеры переходят на SiteConfig/prefix-объект |

## Проверка в фазе 3 (не покрыто юнит-тестами)

- Успешный вызов `db:call` / `db:mutate` на реальном провайдере и таблицах
  (в тестах покрыты whitelist, --force, ошибки, `ProviderInvoker::bind`).

## Критерий готовности фазы 1 (swap-point)

1. Все команды из `bin/cli.old.php` (65) реализованы в новом стеке — кроме тех,
   что явно отнесены к backlog фазы 2 (`structure:check`, `structure:repair`).
2. `site:config:migrate` переносит `site.config.old` → `site.config` без потерь.
3. Паритет-прогон read-only команд на реальном `app/` совпадает.
4. `phpunit` + `phpstan` + `php-cs-fixer` зелёные.
5. `_doc/cli.md` + раздел конфига в `install.md` описывают новую схему.
6. Только после этого: `src/Cli/Commands` → `src/Cli/CommandsOld`, `src/Cli/Next/*` → канонические пути,
   затем удаление `*Old` и `config/site.config.old`.
7. `aicli.php` и `dbcli.php` падают с внятной ошибкой (а не молча «нет доменов»).

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
| skins | `skin:new`, `skin:edit`, `skin:remove`, `skin:list` | готово |
| langs | `lang:list`, `lang:add`, `lang:remove`, `lang:repair`, `lang:sync` | готово |
| sessions | `session:start`, `session:apply`, `session:discard`, `session:list` | готово |
| content | `content:store`, `content:remove`, `content:list`, `content:gc` | готово |
| structure | `structure:check`, `structure:repair` | — |
| trash/system | `trash:clear`, `system:scan`, `system:clean`, `backup:create`, `backup:restore` | готово |
| db | `db:schema`, `db:methods`, `db:describe`, `db:call`, `db:mutate` — готово; `db:migrate`, `dataprovider:add-method` — в работе | частично |
| admin/user | `admin:passwd`, `admin:new`, `user:new` | — |
| прочее | `api:reflect`, `migrate:page-rules`, `assembly:new`/`new`, `ai:edit-page` | — |
| миграция | `site:config:migrate` | — |

## Решения (журнал)

- `admin` — не проект в `domains_app`; `app/.admin` остаётся инфраструктурой.
- Пустой `domains_app` допустим (свежая установка, удаление последнего проекта).
- Настройки проекта живут в `domains_app[key]`; `fillers` в конфиге не существует.
- URL картинки в JSON-блоке — атрибут `content` (не `url`).
