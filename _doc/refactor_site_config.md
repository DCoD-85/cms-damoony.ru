# Рефакторинг CLI на схему `domains_app` (рабочий план)

Живой чек-лист. Обновляется по ходу; после завершения фазы 2 удаляется.

## Цель

Единственная схема конфига — `domains_app` (`folder` + `namespace` + `mirrors` + алиасы).
`domains_real`, `domains_mirrors`, `domains_mirrors_available`, `fillers` — удалены полностью,
без слоя совместимости.

## Фазы

1. **CLI** — все команды на `domains_app`, старый CLI-код удалён. Сайт локально может не работать.
2. **Движок** — `ConfigDomainResolver`, `PageNamespace`, `ContentResponder`, `AppFactory`,
   `StructureChecker` на `SiteConfig`; удаление старых чтений конфига.
3. **Фиксация** — гейты + живой прогон локально.
4. **Боевой** — миграция `site.config` на проде, деплой, проверка.

## Конвенция имён

Каноническое имя — у нового (`bin/cli.php`, `config/site.config`). Старое получает постфикс
`old` и удаляется, когда проверка полностью перенесена на новое.

## Правило переноса тестов

Старый тест удаляется в том же коммите, где его аналог в новом стеке зелёный.
Тесты ядра (`PageCompiler`, `BlockCompiler`, `Fs`, `Paths`, `FileRouter`), не зависящие
от схемы конфига, не удаляются.

## Статус фазы 1

**Фаза 1 закрыта** (2026-11-17), кроме двух команд, отнесённых к фазе 2.

### Готово

- **Конфиг:** `SiteConfig` / `ProjectSettings` / `AliasSettings` / `SiteConfigWriter`,
  строгая валидация (уникальность хостов, `folder` без обхода, языки алиаса ⊆ проекта,
  `prefix` таблиц), миграция `site:config:migrate` (проверена на реальном конфиге).
- **Команды (64 + help):**
  - проекты: `domain:list|new|bind|unbind|remove`, `allias:list|new|remove`, `new --assembly`;
  - контент сайта: `page:*`, `block:*`, `filler:*`, `scheme:*`, `skin:*`, `lang:*`, `content:*`;
  - сессии: `session:start|apply|discard|list`;
  - данные: `db:schema|methods|describe|call|mutate|migrate`, `dataprovider:add-method`;
  - обслуживание: `cli:init`, `admin:passwd`, `user:new`, `system:scan|clean`, `trash:clear`,
    `backup:create|restore`, `api:reflect`, `migrate:page-rules`, `site:config:migrate`.
- **Инфраструктура:** `TargetResolver`, `SessionGuard`, `SessionWorkspace`, `ContentLayout`,
  `ContentRefs`, `Scaffolder`, `Quarantine`, `TrashScanner`, `BlockFileEditor`, `ProviderInvoker`,
  `IdentityStore`, `CliLogger`.
- **Идентификация:** `cli:init` → `var/cli.identity` (0600, проверка владельца на Unix);
  без идентификатора CLI отказывает. Журнал запусков — `var/log/cli/YYYY-MM-DD.log` (JSONL,
  редакция секретов).
- **Документация:** `_doc/cli/` (7 файлов), `install.md` §3.2 приведён к новой схеме,
  `_doc/cli.md` удалён, ссылки обновлены.
- **Результат:** 800 тестов, `phpstan` — no errors, `php-cs-fixer` — 0.

### Осталось в фазе 1 → перенесено в фазу 2

| Что | Причина |
|---|---|
| `structure:check`, `structure:repair` | опираются на `Diagnostics\StructureChecker`, который читает старый конфиг |
| удаление `src/Cli/Commands/*` (2 файла) | исчезнут вместе с `StructureChecker` |
| `bin/aicli.php` (`AiStartHandler`) | падает явно; перевод — вместе с AI-контуром |
| `bin/dbcli.php` (`SchemaGenerate*`) | падает явно; перевод — вместе с движком БД |

## Фаза 2: движок

**Закрыта** (2026-11-17): старых чтений конфига в `src/` не осталось.

| Было | Стало |
|---|---|
| `ConfigDomainResolver` (`domains_real/mirrors`) | `SiteDomainResolver` на `SiteConfig` |
| `PageNamespace::fromSite()` (эвристика) | `project->namespace` |
| `ContentResponder::hostAllowed(..., array)` | `SiteConfig::projectByHost()` |
| `StructureChecker` (Config) | `StructureChecker` (SiteConfig + `FillerState`) |
| `structure:*` в старом CLI | `structure:check`/`structure:repair` в новом CLI |
| `aicli` fail-fast | `SiteConfig::projectKeys()` |
| `dbcli` (`ResolvesRealDomain` trait) | `ResolvesProject` + `project->folder/namespace/storagePrefix()` |
| `ProviderInvoker::providerConfig()` (мост) | `AbstractProvider` принимает готовый префикс таблиц |
| `alliases` — отдельный ключ верхнего уровня | `domains_app.{key}.aliases` — алиасы внутри проекта |
| `src/Cli/Commands/*` (65 файлов) | каталог удалён целиком |

`FrontController` отдаёт страницам `site_config` (SiteConfig); сервис `site` (Config) **удалён** —
страниц, читающих старый конфиг, не осталось (два блока `top` переписаны на `site_config`).

## Проверка в фазе 3 (не покрыто юнит-тестами)

- ~~Успешный вызов `db:call`~~ — проверено на живой БД (`db:call user::by_login`).
- `db:mutate` на реальном провайдере (пишет `audit_log` + префикс таблиц).
- `backup:create` / `backup:restore` на живой MySQL (в тестах — только отказы).
- Живой прогон сайта и админки после перевода движка (локально — сайт 200 OK).

## Решения (журнал)

- **Алиасы живут внутри проекта** (`domains_app.{key}.aliases`): алиас вне проекта структурно
  невозможен, удаление проекта не оставляет сирот. Плоский `alliases` удалён.
- Конфиг в `app/{folder}/` отклонён: маршрутизация host→folder всё равно требует центрального
  индекса, а уникальность хостов — глобальной валидации.
- `admin` — не проект в `domains_app`; `app/.admin` и `admin:new` **удалены**, админка ставится сборкой на уровне домена/алиаса.
- `ai:edit-page` не переносится: она писала в production в обход контура `aicli`.
- CLI идентифицирует владельца (`cli:init`), роли и токены — отдельный шаг.
- Пустой `domains_app` допустим (свежая установка, удаление последнего проекта).
- Настройки проекта живут в `domains_app[key]`; `fillers` в конфиге не существует.
- URL картинки в JSON-блоке — атрибут `content` (не `url`).
- Контент ключуется папкой проекта, а не входным хостом.
