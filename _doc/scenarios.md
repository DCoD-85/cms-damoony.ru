Сценарии проекта Damoony

Ключевые сценарии (Use Cases), которые проверяют работоспособность архитектуры. Сгруппированы по слоям: структура (CLI/файлы), рендеринг (frontend), безопасность, вывод (AJAX), Low-Code, диагностика, миграция, content, skin, AINET, безопасность CLI, Roles/Navigator и AI-слой.

**Легенда:**

- ✅ — работает как описано.
- 🟡 — работает с оговорками (см. ссылку в MANIFEST.md).
- 🔴 — сломан/не реализован.

**Тесты:** 570 тестов, 1671 assertion, 87 тестовых файлов, 269 файлов проверяются PHPStan/CS-Fixer (1 skipped — `LinkTraversalTest` требует dev-сервер `127.0.0.1:8080`, прогоняется в CI).

---

## Сценарии управления структурой (CLI и файловая система)

### S01. Создание домена — ✅

```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

**Ожидание:** создана папка `app/example.ru/`, алиас `www`, root-страница через PageCompiler (`algoritm.php`, `algoritm.rules.json`, `algoritm.editor.json`), схема `www`, базовый блок `center`. Обновлён `site.config` (`domains_real/available/namespace`, `alliases_real/available`).

### S02. Создание дополнительного алиаса — ✅

```bash
php bin/cli.php allias:new --domain=example.ru --name=doc
```

**Ожидание:** создана `app/example.ru/doc/algoritm/` (root через PageCompiler: `algoritm.php`, `algoritm.rules.json`, `algoritm.editor.json`), обновлён `site.config` (`alliases_real`, `alliases_available`).

### S03. Создание глобального блока — ✅

```bash
php bin/cli.php block:new --domain=example.ru --name=top --type=visual
```

**Ожидание:** создана папка `.alliasglobal/top/` с тремя файлами: `top.php`, `top.rules.json`, `top.editor.json`. Путь строится по `realDomain`.

### S04. Создание филлера — ✅

```bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
```

**Ожидание:** созданы `top_www.php`, `top_www.rules.json`, `top_www.editor.json`. В `site.config` добавлен `fillers[domain][allias][block] = true`. Проверка алиаса и блока (конфиг + диск).

### S05. Создание страницы — ✅

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
```

**Ожидание:** создана папка `about/` с тремя файлами: `algoritm_about.php` (наследует `Page`, пустой stub через PageCompiler), `algoritm_about.rules.json` (полные дефолты `available/auth/search/default`), `algoritm_about.editor.json` (пустой). В `algoritm/algoritm.rules.json` добавлен `'about'` в `available`. Родительская страница должна существовать (проверяется).
(Оговорка: страница не рендерит схему по умолчанию, если не указан флаг `--with_schema` или ручной вызов `SchemeRenderer`.)

### S06. Создание схемы — ✅

```bash
php bin/cli.php scheme:new --domain=example.ru --name=www
```

**Ожидание:** созданы `www.php`, `www.rules.json`, `www.editor.json`.

### S06a. Копирование схемы — ✅

```bash
php bin/cli.php scheme:copy --domain=example.ru --name=www
```

**Ожидание:** созданы `www_copy.php`, `www_copy.rules.json`, `www_copy.editor.json`. При повторном вызове — `www_copy_1`, `www_copy_2`, ... (уникальные имена). Копируется 3 файла. Если `.rules.json` отсутствует у исходной схемы — создаётся дефолт `{"enabled":true}`.

### S06b. Удаление схемы (в карантин) — ✅

```bash
php bin/cli.php scheme:remove --domain=example.ru --name=www --force
```

**Ожидание:** 3 файла перемещены в `.alliasscheme/www_trash_<timestamp>/`. Без `--force` — отмена. Idempotent: если схемы нет — warning, exit 0. Очистка — `trash:clear`.

### S06c. Сохранение визуальной схемы — ✅

```bash
php bin/cli.php scheme:save --domain=example.ru --name=www --scheme_json='{"children":[]}'
```

**Ожидание:** обновлён `www.editor.json` (поле `"scheme"`, `"fields"` не трогается), скомпилирован `www.php` через SchemeCompiler. Принимает `--scheme_json_b64` (для AI). Требует существующий `editor.json`.

### S07. Удаление домена — ✅

```bash
php bin/cli.php domain:remove --domain=example.ru --force
```

**Ожидание:** папка домена перемещена в карантин (`example.ru_trash_timestamp`), конфиг `site.config` полностью очищен (16 ключей: `domains_real/available/namespace/titles/skins/skins_allowed/stubs/scheme`, `alliases_real/available/langs/titles/stubs/skins/skins_allowed`, `fillers`). Без `--force` — отмена. Idempotent: нет папки + нет в конфиге → warning, exit 0.

### S08. Запись контента в страницу — ✅

```bash
php bin/cli.php page:write --domain=example.ru --allias=www --name=about --json='[{"type":"h1","content":"Title"}]'
```

**Ожидание:** контент компилируется через PageCompiler и записывается в `algoritm_about.php`.
Спец. имя: `--name='__root__'` используется для записи на главную страницу аллиаса (`/`). Флаг `--parent` несовместим с `__root__` (ошибка).

### S09. Чтение кода страницы — ✅

```bash
php bin/cli.php page:read --domain=example.ru --allias=www --name=about
```

**Ожидание:** выводит сгенерированный PHP-код страницы (полезно для AI).
Спец. имя: `--name='__root__'` читает главную страницу аллиаса.

### S10. Очистка карантина — ✅

```bash
php bin/cli.php trash:clear --force --older=7d
```

**Ожидание:** удалены папки trash, старше 7 дней. По умолчанию — dry-run. Обходит **6 уровней** карантина: `app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`. Флаги: `--force` (боевой), `--older` (7d/24h/30m), `--domain` (только один домен). Symlink-safe `Fs::rmrf`.

---

## Сценарии Content (`/content/`)

### S50. Запись контента через CLI — ✅

```bash
php bin/cli.php content:store --domain=example.ru --allias=www --category=coffee --data_b64=<base64> --mime=image/png --alt="Coffee cup"
```

**Ожидание:** создан `/content/example.ru/www/coffee/<hash>.png` + `<hash>.meta.json` (`source_url`, `alt`, `size`, `mime`, `width`, `height`, `page_refs`, `hash`, `downloaded_at`). Валидация: `domain` в `config/agent.config.allowed_domains`, размер ≤ 10 MB, размеры ≥ 200×200, MIME ∈ {image/jpeg, image/png, image/gif, image/webp, image/svg+xml}. **Вне webroot.**

### S50a. Запись контента через `--data_file` — ✅

```bash
php bin/cli.php content:store --domain=example.ru --allias=www --category=coffee --data_file=/tmp/image.png --mime=image/png
```

**Ожидание:** то же, что S50, но байты читаются из файла (обход Windows CMD limit ~8 KB).

### S51. Дедупликация контента — ✅

```bash
php bin/cli.php content:store --domain=example.ru --allias=www --category=coffee --data_b64=<те_же_байты> --mime=image/png
```

**Ожидание:** если `hash` уже есть — возвращает существующий путь (warning «deduplicated»), файл не перезаписывается.

### S52. Удаление контента (в карантин) — ✅

```bash
php bin/cli.php content:remove --domain=example.ru --allias=www --category=coffee --hash=a3f5c1e9 --force
```

**Ожидание:** `<hash>.*` + `<hash>.meta.json` перемещены в `/content/.trash/example.ru_www_coffee_a3f5c1e9_trash_<ts>/`. Без `--force` — отмена. Idempotent: если контента нет — warning, exit 0. Очистка — `trash:clear` (уровень 5).

### S53. Список контента — ✅

```bash
php bin/cli.php content:list --domain=example.ru [--allias=www] [--category=coffee]
```

**Ожидание:** список `{path, mime (из meta.json), size, hash, page_refs}`. Без `--allias` — рекурсивный обход по всем алиасам. Без `--category` — все категории.

### S54. Content (`/content/`) и Skin (`www/media/.../skin/`) — две зоны, не смешиваются — ✅

**Ожидание:**

- `/content/` — runtime AI-контент (вне webroot, `.gitignore`).
- `/content/.trash/` — карантин `content:remove` (runtime, `.gitignore`).
- `www/media/admin/` — инфраструктура (git, руками).
- `www/media/shared/` — инфраструктура (git, руками).
- `www/media/<domain>/<allias>/skin/` — скины (git, руками).
- `www/media/.trash/` — карантин `skin:remove` (runtime, `.gitignore`).

**Тест:** `git check-ignore /content/example.ru/www/coffee/abc.png` → ignored; `git check-ignore www/media/damoony.ru/www/skin/default/css/main.css` → NOT ignored.

---

## Сценарии Skin (`www/media/<domain>/<allias>/skin/`)

### S74. Создание скина — ✅

```bash
php bin/cli.php skin:new --domain=example.ru --allias=www --name=default
```

**Ожидание:** созданы `www/media/example.ru/www/skin/default/{css/main.css, js/main.js, img/, skin.json}`. Git-tracked (не `.gitignore`).

### S75. Список скинов — ✅

```bash
php bin/cli.php skin:list --domain=example.ru --allias=www
```

**Ожидание:** список имён скинов + метаданные из `skin.json`.

### S76. Редактирование скина — ✅

```bash
php bin/cli.php skin:edit --domain=example.ru --allias=www --name=default --file=css/main.css --content="body{margin:0}"
```

**Ожидание:** обновлён `css/main.css`. Валидация: `file` ∈ {`css/main.css`, `js/main.js`}, размер ≤ 1 MB, блок `@import` и `javascript:` URL.

### S76a. Skin:edit — отклонение недопустимого файла — ✅

```bash
php bin/cli.php skin:edit --domain=example.ru --allias=www --name=default --file=shell.php --content="..."
```

**Ожидание:** отклонено (error: «Invalid file (allowed: css/main.css, js/main.js)»). Вне whitelist — не записывается.

### S77. Удаление скина (в карантин) — ✅

```bash
php bin/cli.php skin:remove --domain=example.ru --allias=www --name=default --force
```

**Ожидание:** `www/media/example.ru/www/skin/default/` перемещён в `www/media/.trash/example.ru_www_skin_default_trash_<ts>/`. Без `--force` — отмена. Idempotent: если скина нет — warning, exit 0. Очистка — `trash:clear` (уровень 6).

---

## Сценарии рендеринга и роутинга (Frontend)

### S11. Успешный рендеринг главной страницы — ✅

**Вход:** GET `http://example.ru/`
**Шаги:** DomainResolver → FileRouter (читает `algoritm/algoritm.rules.json`) → PageHandler → SchemeRenderer (вызванный из кода страницы).
**Ожидание:** HTTP 200, HTML-документ, собранный из слотов схемы.

### S12. Рендеринг 404 — ✅

**Вход:** GET `http://example.ru/unknown-path`
**Ожидание:** HTTP 404.

### S13. Режим обслуживания (503) — ✅

**Вход:** GET `http://example.ru/` при `"domains_available": false`.
**Ожидание:** HTTP 503, HTML-заглушка из `stubs/`.

### S14. Проверка статуса алиаса — ✅

**Вход:** GET `http://doc.example.ru/` при `"alliases_available": {"example.ru": {"doc": false}}`.
**Ожидание:** HTTP 503 для `doc`, `www` работает.

### S15. Доступ к админке при выключенном сайте — ✅

**Вход:** GET `http://example.ru/admin/` при `"domains_available": false`.
**Ожидание:** админка открывается.

### S15a. Отдача Content через `www/content.php` (200, ETag) — ✅

**Вход:** GET `/content/example.ru/www/coffee/<hash>.png`
**Ожидание:** HTTP 200, `Content-Type: image/png`, `Cache-Control: public, max-age=31536000, immutable`, `ETag: "<hash>"`, `Last-Modified`, `X-Content-Type-Options: nosniff`.

### S15b. Content — 304 Not Modified — ✅

**Вход:** GET `/content/...` с `If-None-Match: "<hash>"`.
**Ожидание:** HTTP 304, тело пустое.

### S15c. Content — POST отклоняется — ✅

**Вход:** POST `/content/example.ru/www/coffee/<hash>.png`.
**Ожидание:** HTTP 405, `Allow: GET, HEAD`.

### S15d. Content — auth по `page_refs` — ✅

**Вход:** GET `/content/example.ru/www/protected/<hash>.jpg`, в `meta.json` есть `page_refs: ["<page-with-auth>"]`.
**Ожидание:** если юзер не залогинен — HTTP 302 → `/login?return=<originalUri>`. Если залогинен — 200.

### S15e. Content — host mismatch → 404 — ✅

**Вход:** GET `http://other.com/content/example.ru/www/coffee/<hash>.png`.
**Ожидание:** HTTP 404 (не палим чужие домены, `hostAllowed`).

### S15f. Content — SVG-CSP — ✅

**Вход:** GET `/content/example.ru/www/icons/<hash>.svg`
**Ожидание:** HTTP 200, `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'` (защита от inline-скриптов).

---

## Сценарии безопасности и авторизации

### S16. Защита страницы авторизацией — ✅

**Вход:** GET `http://example.ru/about`, где `algoritm_about.rules.json`: `"auth": true`.
**Ожидание:** HTTP 302 → `/login?return=/about`.

### S17. Успешный вход (Login) — ✅

**Вход:** POST `/login` с CSRF-токеном.
**Ожидание:** HTTP 302, пользователь авторизован.

### S18. Защита от CSRF — ✅

**Вход:** POST `/login` без CSRF-токена.
**Ожидание:** HTTP 403. Destructive-эндпоинты также требуют POST+CSRF (GET → 405).

### S19. Управление привязками доменов — ✅

**Вход:** `domains_real["192.168.1.10"] = "example.ru"`, запрос по IP.
**Ожидание:** сайт открывается, порт отрезается.

---

## Сценарии вывода (AJAX)

### S20. Ответ в формате JSON (Min) — ✅

**Вход:** GET `/api-endpoint` с `X-Requested-With: XMLHttpRequest`.
**Ожидание:** HTTP 200, JSON.

### S21. Ответ в формате JSON (Ajax Layout) — ✅

**Вход:** аналогично S20.
**Ожидание:** JSON с полями `error, address, layers`.

---

## Сценарии Low-Code и Админки

### S22. Редактирование страницы через CodeMirror — ✅

**Вход:** кнопка «Код» у страницы в админке.
**Ожидание:** открывается редактор CodeMirror с `algoritm_about.php`.

### S23. Системная диагностика (Structure Check) — ✅

**Вход:** `php bin/cli.php structure:check`.
**Ожидание:** проверяет наличие всех 3 файлов (`algoritm_{name}.{php,rules.json,editor.json}`), наличие `algoritm/algoritm.rules.json` (root), отсутствие устаревших `{dir}/.rules.json`, стыковку филлер↔слот.
**Severity-aware:**
- `✗` (error) — отсутствует `.php`, отсутствует root `algoritm.rules.json`, отсутствует схема.
- `⚠` (warning) — отсутствует `.editor.json`, отсутствует `.rules.json` у блока/схемы, устаревший `.rules.json`, филлер без слота.

Команда возвращает OK (exit 0), если нет errors (warnings допускаются).

### S24. Безопасная смена пароля — ✅

**Вход:** профиль админки → текущий пароль, новый.
**Ожидание:** пароль изменён (валидация: минимум 8 символов).

### S25. Развёртывание по IP — ✅

**Вход:** GET `http://192.168.1.10:8080/`, `domains_real["192.168.1.10"] = "example.ru"`.
**Фактически:** ✅ работает. Порт и trailing-dot нормализуются.

### S26. Визуальное сохранение схемы с вложенностью — ✅

**Вход:** открыть схему (`www`) в визуальном редакторе, сохранить.
**Фактически:** ✅ работает. Дерево сохраняется без схлопывания благодаря `www.editor.json` и Drag&Drop UI. Сохранение — через CLI `scheme:save`.

### S27. Переключение Auth/Search в дереве страниц — ✅

**Вход:** клик по пилюле Auth (🔒) или Search (🔍) в админке.
**Ожидание:** POST-запрос → CLI `page:toggle --name=... --parent=... --key=auth|search --value=0|1`. Обновление `algoritm_{name}.rules.json`.

### S28. default(): null — ✅

**Вход:** ручная страница возвращает `null` из `default()`.
**Фактически:** ✅ закрыто (stage-4). `PageHandler::toResponse()` возвращает 204 No Content.

### S29. CSRF-атака на удаление схемы — ✅

**Вход:** GET `/admin/?p=scheme:delete&domain=example.ru&name=www`.
**Фактически:** ✅ закрыто. Отдается 405 Method Not Allowed.

### S30. Защита от RCE (raw PHP) — ✅

**Вход:** попытка сохранить блок с типом `custom` (сырой PHP) при `security.allow_php_blocks = false`.
**Ожидание:** RegionCompiler выбрасывает RuntimeException, сохранение блокируется.

---

## Сценарии `*:list` (инспекция состояния через CLI и AICLI)

### S78. `*:list` команды — ✅

```bash
php bin/cli.php domain:list
php bin/cli.php allias:list --domain=example.ru
php bin/cli.php page:list --domain=example.ru --allias=www
php bin/cli.php scheme:list --domain=example.ru
php bin/cli.php block:list --domain=example.ru
php bin/cli.php filler:list --domain=example.ru --allias=www
```

**Ожидание:** все команды возвращают `Result` с `data`. domain/allias/scheme/block/filler:list — массив строк; page:list, content:list, skin:list — массив объектов (разные поля). `--json` даёт машинный вывод. `allias:list` группирует по real (значение), ключ = вариант алиаса (включая `''` → `www`). `domain:list` возвращает уникальные real (без `''`).

### S78a. `page:list --json` — ✅

```bash
php bin/cli.php page:list --domain=example.ru --allias=www --json
```

**Ожидание:** JSON-массив страниц с полями `name`, `path`, `auth`, `search`, `default`. Используется AI в ролях `parser`/`inspector`/`planner`/`reporter`.

---

## Сценарии миграции page-rules

### S35. Миграция page-rules — ✅

```bash
php bin/cli.php migrate:page-rules [--dry]
```

**Ожидание:** переводит старые `.rules.json` на новую модель (`algoritm_{name}.rules.json`). Идемпотентна.
**Шаги:**
1. root `algoritm/.rules.json` → `algoritm/algoritm.rules.json` (с union available).
2. подпапки `{dir}/.rules.json` → `{dir}/algoritm_{name}.rules.json` (с union available).
3. нормализация полей (`available/auth/search/default`) для всех `algoritm_*.rules.json`.
4. создание пустых `.editor.json` для существующих `.php`.

`--dry` — предпросмотр без записи.
Повторный запуск — 0/0/0 (идемпотентно).

### S36. Severity-aware structure:check — ✅

```bash
php bin/cli.php structure:check
```

**Ожидание:** `✗` только для критических (отсутствует `.php`, отсутствует root `algoritm.rules.json`, отсутствует схема). `⚠` для предупреждений.
Если только warnings — команда возвращает OK (exit 0).

### S37. Toggle через --name — ✅

```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --name=about --key=auth --value=1
```

**Ожидание:** обновлён `algoritm_about.rules.json`. Для главной — `--name='__root__'`.
(Старый `--path` не поддерживается с новой моделью.)

---

## Сценарии безопасности CLI (аудит)

### S39. Карантин на 6 уровнях (trash:clear) — ✅

```bash
php bin/cli.php trash:clear --force
```

**Ожидание:** находит и удаляет карантины на всех уровнях:

- `app/<domain>_trash_<ts>/` (domain:remove)
- `app/<domain>/<allias>_trash_<ts>/` (allias:remove)
- `app/<domain>/.alliasscheme/<name>_trash_<ts>/` (scheme:remove)
- `app/<domain>/.alliasglobal/<name>_trash_<ts>/` (block:remove)
- `/content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/` (content:remove)
- `www/media/.trash/<domain>_<allias>_skin_<name>_trash_<ts>/` (skin:remove)

Фильтры `--older` и `--domain`. Dry-run по умолчанию. Symlink-safe `Fs::rmrf`.

### S40. Карантин блока (block:remove) — ✅

```bash
php bin/cli.php block:remove --domain=example.ru --name=top --force
```

**Ожидание:** папка `.alliasglobal/top/` перемещена в `top_trash_<timestamp>/`. Без `--force` — отмена. Idempotent: если блока нет — warning, exit 0.

### S41. Filler:toggle idempotent — ✅

```bash
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=1
```

**Ожидание:** если значение уже `1` — файл `site.config` не перезаписывается. Проверяется, что блок существует на диске (не пишем мусор в конфиг).

### S42. Path traversal guard в CLI — ✅

```bash
php bin/cli.php domain:remove --domain=a..ru --force
```

**Ожидание:** отклонено (error). Все CLI-команды с `--domain`/`--name` проверяют `str_contains('..')` и regex.

### S43. realDomain при построении путей — ✅

**Вход:** `domains_real["damoony3.ru"] = "damoony.ru"`. Команда `block:new --domain=damoony3.ru --name=top`.
**Ожидание:** папка создана в `app/damoony.ru/.alliasglobal/top/`, а не в `app/damoony3.ru/`.

### S44. db:migrate --dir path traversal guard — ✅

```bash
php bin/cli.php db:migrate --dir=/etc
```

**Ожидание:** отклонено. `--dir` ограничен подпапками `migrations/` (realpath check).

### S45. admin:passwd валидация — ✅

```bash
php bin/cli.php admin:passwd --name=admin --password=short
```

**Ожидание:** отклонено (пароль <8 символов). Login — regex 3-32 `[a-zA-Z0-9_.-]`.

### S46. user:new валидация — ✅

```bash
php bin/cli.php user:new --login=admin --email=bad-email --password=short
```

**Ожидание:** отклонено (email не проходит `FILTER_VALIDATE_EMAIL`, пароль <8). Уникальность login и email.

### S47. structure:repair генерирует валидный PHP — ✅

```bash
php bin/cli.php structure:repair --domain=example.ru
```

**Ожидание:** для отсутствующих root-страниц создаётся `algoritm.php` через PageCompiler (extends Page, корректный namespace, без битого `use Algoritm`).

### S48. VarsExtractCommand удалён — ✅

**Вход:** `grep -n "vars:extract" bin/cli.php`.
**Ожидание:** пусто. Команда удалена (мёртвый код), тест удалён. Миграция старой модели — `migrate:page-rules`.

### S49. Fs::rmrf — единый источник истины — ✅

**Вход:** тест `FsTest::testRmrfRemovesDirWithFiles` (папка с файлами внутри).
**Ожидание:** файлы внутри удаляются, папка удаляется. Windows-safe (`clearstatcache`), symlink-safe (unlink, не следуем). Используется в `trash:clear` и Cache. (До рефакторинга был баг: файлы внутри карантина не удалялись.)

---

## Сценарии AI-Слоя (Agent Integration)

### S31. Безопасный запрос к AI-фасаду — ✅

**Вход:** `echo '{"command": "read_test_file", "args": {"filename": "agent_test.txt"}}' | php bin/aicli.php`
**Ожидание:** JSON-ответ `{"ok": true, "data": {...}}`. Защита от Path Traversal.

### S32. Автономное лечение ошибки агентом — ✅

**Вход:** Python-цикл (Ollama) отправляет запрос агенту: "Проверь статус и исправь ошибки".
**Шаги:** AI вызывает `engine_status`, находит ошибку (Филлер включен, но слота нет), вызывает `filler_toggle`.
**Фактически:** ✅ работает. Агент сам диагностирует и лечит систему.

### S33. Генерация многостраничного сайта агентом — ✅

**Вход:** Python-цикл отправляет бриф: "Создай 10-страничный сайт приюта с меню и стилями".
**Шаги:**
1. AI генерирует JSON-массив (21 команда: `domain:new`, `allias:new`, `page:new` × 9, `page:write` × 10).
2. Python очищает JSON от висячих запятых.
3. `aicli.php` выполняет команды, передавая JSON-контент через base64.
4. PageCompiler генерирует безопасный PHP-код страниц.

**Фактически:** ✅ работает (протестировано на Qwen 2.5 Coder 7B через `agent_test.py`). Сайт создается за один шаг.

### S33a. Генерация сайта с картинками (AINET) — ✅

**Вход:** Python-цикл отправляет бриф: "Создай сайт приюта с картинками животных".
**Шаги:**
1. AI вызывает `image:search --query="dogs shelter" --count=3` → wikimedia results.
2. AI выбирает url (width/height ≥ 800, license CC/PD).
3. AI вызывает `image:download --url=<url> --domain=example.ru --allias=shelter --category=dogs --alt="Собака"` → aicli: AINET (bytes) → `shell_exec` → `cli.php content:store` → `local_url`.
4. AI вызывает `page:write --json='[{"type":"h1",...},{"type":"img","content":"<local_url>","alt":"Собака"}]'`.

**Фактически:** ✅ работает. Картинки локальные (`/content/`), URL стабильны.

### S34. Запрет опасных команд для AI — ✅

**Вход:** Агент пытается вызвать команду `user:new` (создать админа) через `aicli.php`.
**Ожидание:** `aicli.php` возвращает `{"ok": false, "error": "Command 'user:new' is not allowed for role '*'.", "hint": "Allowed: ..."}`. Команда не выполняется.

### S38. Защита AI от записи в чужой домен — ✅

**Вход:** Агент вызывает `page:write` с `domain=example.ru`, где `example.ru` НЕ в `config/agent.config` (`allowed_domains`).
**Ожидание:** `aicli.php` возвращает `{"ok": false, "error": "Security violation: Domain 'example.ru' is not in the allowed list."}`. Команда не выполняется.

### S39a. Roles + Navigator — ✅

**Вход:** `bin/aicli.php` с разными ролями.
**Ожидание:**

- 9 ролей: `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter` (источник — `src/Ai/Roles.php`).
- Роль `*` (default) = union всех ролей + специфичные (`read_test_file`, `filler_toggle`, `net:*`, `skin:remove`, `*:list`).
- Navigator (`src/Ai/Navigator.php`): `next[]` в каждом ответе. `OFFERABLE = [domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, done]`.
- `next[]` фильтруется по whitelist роли (defense in depth).

### S81. `role="*"` — надмножество всех ролей — ✅

**Вход:** `echo '{"command":"block:new","role":"*","args":{"domain":"invalid..ru","name":"x"}}' | php bin/aicli.php`
**Ожидание:** команда **проходит** whitelist `*` (может упасть на валидации `invalid..ru`, но НЕ на «is not allowed for role»). Инвариант покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`.

### S82. Navigator: `after(allias:new) → skin:new`, `after(skin:new) → skin:edit` — ✅

**Вход:** `{"command":"allias:new","role":"architect","args":{"domain":"example.ru","name":"coffee"}}`
**Ожидание:** в ответе `next[]` содержит `skin:new` (с `args.domain`, `args.allias`, `args.name=default`), `page:new`, `done`. Тест: `NavigatorTest`.

---

## Сценарии AINET (Сеть, SSRF)

### S60. net:status — ✅

```bash
echo '{"command": "net:status", "args": {}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":true,"data":{"providers":["openverse","wikimedia"],"default_provider":"wikimedia","whitelist_count":N,"cache_size_mb":0,"cache_entries":0,"rate_limit":{"max":30,"window":"1m"}}}`.

### S61. image:search (wikimedia default) — ✅

```bash
echo '{"command": "image:search", "args": {"query": "coffee", "count": 2}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":true,"data":{"provider":"wikimedia","results":[{"url":"https://upload.wikimedia.org/...","preview_url":"https://thumb.wikimedia.org/...","width":1600,"height":1200,"license":"CC BY-SA 2.0","author":"...","source":"wikimedia","title":"File:..."}]}}`.
**Автофоллбэк:** wikimedia (default) → openverse. Если wikimedia timeout/0 → openverse. Если оба fail — error.

### S62. image:search (явный provider) — ✅

```bash
echo '{"command": "image:search", "args": {"query": "coffee", "count": 2, "provider": "openverse"}}' | php bin/aicli.php
```

**Ожидание:** явный provider = без fallback. Если Openverse timeout — error (не fallback).

### S63. image:download через aicli (AINET → CLI) — ✅

```bash
echo '{"command": "image:download", "args": {"url": "https://upload.wikimedia.org/...", "domain": "example.ru", "allias": "www", "category": "coffee", "alt": "Coffee cup"}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":true,"data":{"local_url":"/content/example.ru/www/coffee/<hash>.png","hash":"...","path":"content/example.ru/www/coffee/<hash>.png","size":...,"mime":"image/png","width":...,"height":...,"cached":false}}`. Внутри: `AinetFacade.downloadImage` (bytes) → `shell_exec('cli.php content:store ...')` → FS.

### S64. image:download с чужим доменом — ✅

```bash
echo '{"command": "image:download", "args": {"url": "https://upload.wikimedia.org/x.jpg", "domain": "evil.com", "category": "test"}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":false,"error":"Security violation: Domain 'evil.com' is not in the allowed list.","hint":"Add domain to config/agent.config allowed_domains"}`.

### S65. fetch (Wikipedia JSON) — ✅

```bash
echo '{"command": "fetch", "args": {"url": "https://ru.wikipedia.org/api/rest_v1/page/summary/Кофе", "type": "json"}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":true,"data":{"json":{...},"status":200,"cached":false}}`. Второй вызов → `cached:true` (Cache TTL 7 дней).

### S66. fetch не из whitelist — ✅

```bash
echo '{"command": "fetch", "args": {"url": "https://evil.com/x"}}' | php bin/aicli.php
```

**Ожидание:** `{"ok":false,"error":"Domain 'evil.com' not in whitelist","hint":"Add 'evil.com' to config/ainet.config allowed_domains"}`.

### S67. SSRF-защита (private IP) — ✅

**Вход:** `url = "http://127.0.0.1/admin"` или `"http://169.254.169.254/latest/meta-data"`.
**Ожидание:** `{"ok":false,"error":"Private IP blocked: 127.0.0.1","hint":"SSRF protection: cannot fetch internal resources"}`.

### S68. SSRF-защита (redirect на private IP) — ✅

**Вход:** `api.github.com` отвечает 302 → `http://127.0.0.1/admin`.
**Ожидание:** HttpClient не следует редиректу автоматически (`CURLOPT_FOLLOWLOCATION=false`), `UrlGuard.validate(Location)` → Private IP blocked → error.

### S69. Whitelist URL в config/ainet.config — ✅

**Вход:** `config/ainet.config.allowed_domains = ['commons.wikimedia.org']`.
**Ожидание:** `fetch` к `api.github.com` → error «Domain 'api.github.com' not in whitelist».

### S70. AINET init failed (fail-safe) — ✅

**Вход:** `config/ainet.config` битый (invalid JSON), команда `net:status`.
**Ожидание:** `{"ok":false,"error":"AINET init failed: ...","hint":"Fix config/ainet.config or create it from ainet.config.example"}`. Не падает, не пускает.

### S71. Cache hit — ✅

**Вход:** два одинаковых `image:search` или `fetch`.
**Ожидание:** первый → `cached:false` (HTTP), второй → `cached:true` (Cache), HTTP-запрос один раз.

### S72. openverse 301 → .org — ✅

**Вход:** `image:search` с `provider=openverse`.
**Ожидание:** `OpenverseProvider.ENDPOINT = https://api.openverse.org/v1/images/`. Старый `.engineering` → 301 (redirect), но мы уже на `.org`. Timeout из РФ → fallback на wikimedia (для default provider).

### S73. bin/ainet.php standalone — ✅

```bash
echo '{"command": "net:status", "args": {}}' | php bin/ainet.php
```

**Ожидание:** работает как отдельный фасад для отладки. НЕ в whitelist `aicli.php`. AI не знает про этот файл.

---

## Покрытие тестами

| Сценарий | Покрыт | Где |
|---|---|---|
| S01–S10 | ✅ | `_dev/tests/Unit/Cli/` (BlockCommands, SchemeNew, StructureRepair, PageWrite, PageNew, PageCommands, DomainNew, DomainRemove, AlliasCommands) |
| S06a–S06c | ✅ | `_dev/tests/Unit/Cli/SchemeCopyCommandTest`, `SchemeRemoveCommandTest`, `SchemeSaveCommandTest` |
| S11–S15 | ✅ | `_dev/tests/Unit/App/` (FrontController), `Scenarios/` (AjaxScenario, IndexRenderScenario) |
| S15a–S15f | ✅ | `_dev/tests/Unit/Content/ContentResponderTest.php` |
| S16–S19 | ✅ | `_dev/tests/Unit/Admin/AuthTest.php`, `Security/CsrfTest.php` |
| S20–S21 | ✅ | `_dev/tests/Unit/Response/`, `Scenarios/AjaxScenarioTest` |
| S22–S23 | ✅ | `_dev/tests/Unit/Cli/StructureCheckCommandTest.php`, `Diagnostics/StructureCheckerTest.php` |
| S24–S30 | ✅ | Закрыто, регрессии покрыты |
| S31–S34 | ✅ | `_dev/tests/Unit/Ai/AicliTest.php` (proc_open, offline) |
| S35 | ✅ | CLI-прогон + `Diagnostics/StructureCheckerTest` (legacy `.rules.json` warning) |
| S36 | ✅ | `_dev/tests/Unit/Cli/StructureCheckCommandTest.php` |
| S37 | ✅ | `_dev/tests/Unit/Cli/PageCommandsTest.php` |
| S38 | ✅ | `_dev/tests/Unit/Ai/AicliTest.php` (testImageDownloadDomainNotInAgentConfig, testPageWritePathTraversalInDomain) |
| S39 | ✅ | `_dev/tests/Unit/Cli/TrashClearCommandTest.php` (testCollectsDomainTrash, testCollectsSchemeTrash, testCollectsBlockTrash, testCollectsContentTrash, testCollectsSkinTrash) |
| S40 | ✅ | `_dev/tests/Unit/Cli/BlockCommandsTest.php` |
| S41 | ✅ | `_dev/tests/Unit/Cli/FillerCommandsTest.php` |
| S42 | ✅ | Тесты всех CLI-команд (`testRejectsPathTraversal`) |
| S43 | ✅ | CLI-прогон (realDomain в block/filler/scheme/skin/content) |
| S44 | ✅ | CLI-прогон (db:migrate --dir) |
| S45 | ✅ | CLI-прогон (admin:passwd) |
| S46 | ✅ | CLI-прогон (user:new) |
| S47 | ✅ | `_dev/tests/Unit/Cli/StructureRepairCommandTest.php` |
| S48 | ✅ | `bin/cli.php` (grep пуст), `VarsExtractScenarioTest` удалён |
| S49 | ✅ | `_dev/tests/Unit/Files/FsTest.php` (6 тестов) |
| S50–S53 | ✅ | `_dev/tests/Unit/Cli/ContentStoreCommandTest`, `ContentRemoveCommandTest`, `ContentListCommandTest` |
| S54 | ✅ | `www/media/.gitignore` + `/content/` в root `.gitignore` (git check-ignore вручную) |
| S60–S72 | ✅ | `_dev/tests/Unit/Ainet/` (UrlGuardTest, DomainWhitelistTest, HttpClientTest, CacheTest, AinetConfigTest, AinetFacadeTest, ImageSearch/*) + `_dev/tests/Unit/Ai/AicliTest.php` (testNetStatusIsWhitelisted, testNetCommandsInWhitelistHint) |
| S73 | ✅ | CLI-прогон (`php -l` + `echo net:status`) |
| S74–S77 | ✅ | `_dev/tests/Unit/Cli/SkinNewCommandTest`, `SkinListCommandTest`, `SkinEditCommandTest`, `SkinRemoveCommandTest` |
| S78–S78a | ✅ | `_dev/tests/Unit/Cli/ListCommandsTest.php`, `DomainListCommandTest`, `AlliasListCommandTest`, `PageListCommandTest` |
| S81 | ✅ | `_dev/tests/Unit/Ai/RolesTest.php` (testWildcardIsSupersetOfAllRoles) |
| S82 | ✅ | `_dev/tests/Unit/Ai/NavigatorTest.php` |

---

## Итог

**Работает как задумано (✅): 78 из 78 сценариев.**
**Частично (🟡): 0.**
**Сломано (🔴): 0.**

Проект полностью избавился от критических багов. Архитектура CLI-First (с единым источником истины) и AI-Native (с безопасным фасадом) работает предсказуемо.

**AI успешно генерирует многостраничные сайты через безопасный JSON** (10-страничный сайт с картинками — за 1 шаг), не имея доступа к сырому PHP. **9 ролей + Navigator (`next[]` HATEOAS)** направляют AI по безопасному пути. `role="*"` — надмножество всех ролей (инвариант покрыт тестом).

**Модель `.rules.json` унифицирована** (`algoritm_{name}.rules.json`), миграция автоматизирована (`migrate:page-rules`).

**CLI-аудит (37 команд)** закрыл path traversal, realDomain-баги, карантины на **6 уровнях**, idempotent remove. **Content (`/content/`)** — вне webroot, отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). **Skin (`www/media/.../skin/`)** — git-tracked, управляется через `skin:*`.

**AINET** добавил безопасный выход в интернет (SSRF-защита, whitelist, кэш, `default=wikimedia`, `read_timeout 20s`), **content** — локальное хранилище картинок (дедупликация по hash, две зоны с skin).