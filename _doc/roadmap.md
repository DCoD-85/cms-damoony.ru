Roadmap — План развития Damoony Engine

**Статус:** В разработке.
**Дата:** 2 ноября 2026 (audit sync).
**Отметка:** ⚠️ Документ описывает будущие планы. Текущее состояние проекта — в `manifest.md`, `readme.md`, `architecture.md`.

---

## 1. Честная оценка реальности

### Что реально сделать за 1 неделю (быстрые победы)

- ✅ **AICLI**: 9 ролей (`src/Ai/Roles.php`) + Navigator (`src/Ai/Navigator.php`, `next[]` HATEOAS) + `*:list` (6 команд) — **выполнено**.
- ✅ **AICLI**: `skin:new/list/edit/remove` в whitelist (architect/designer) — **выполнено**.
- ✅ **AICLI**: `scheme:save` в whitelist designer — **выполнено** (в CLI; в whitelist `*` — через union ролей).
- **AI-Слой**: Реализовать механизм транзакций (Rollback) в Python-цикле агента при падении одной из команд — 2–3 дня.
- **AI-Слой**: Разбиение длинных задач на батчи (для слабых моделей типа Qwen 7B) — 1 день.
- **AICLI**: Расширение whitelist (`block:edit`, `filler:edit`, `skin:bundle`) с валидацией аргументов — 1 день.
- **AINET**: Rate limit 30/min применить на уровне AinetFacade (сейчас только в конфиге) — 4 часа.
- **Media/Content**: orphan cleanup (неиспользуемые hash) — 4–6 часов.
- SEO-модуль (sitemap, robots, meta, OG) — 2–3 дня.
- Аналитика (GA, Метрика через настройки домена) — 4–6 часов.
- UTM-автодобавление — 1–2 часа.
- Формы + Лиды (отправка в SMTP/Telegram) — 1–2 дня.
- Профиль «Корпоративный» (готовые схемы/страницы) — 1 день.

**Итого за неделю:** Проект становится пригодным для продаж под маркетологов. Realistic.

### Что займёт 1–2 месяца (серьёзные модули)

- **Выбор AI**: Бенчмарк 4–5 локальных моделей на GPU 8GB (Qwen 2.5 Coder 7B/14B, Llama 3.1 8B, Mistral 7B, DeepSeek Coder 7B) — 1 неделя.
- **Тестовый прогон**: Формализовать протокол (5 сценариев, метрики) — 3–5 дней.
- **UX**: Взаимодействие пользователь — инструкции — агент — обратная связь (UI в админке) — 1–2 недели.
- **AINET**: POST/PUT/DELETE, парсинг HTML (для скриншотов/документации) — 1 неделя.
- **Content**: orphan cleanup (неиспользуемые hash), CDN для больших сайтов — 3–5 дней.
- **Skin**: UI-менеджер скинов в админке (CLI уже есть) — 3–5 дней.
- Профиль «Магазин» (каталог, корзина, оформление заказа) — 5–7 дней.
- Кеширование страниц (opcache + HTTP-кеш) — 1–2 дня.
- SPA-админка (зеркальные URL, fetch без перезагрузки) — 3–5 дней.
- Перенос админки в `app/admin/` (полная изоляция) — 2–3 дня.
- Профиль «Портал» (роли admin/editor/manager, API) — 2–3 недели (критические риски, ролевая модель).

---

## 2. План действий (Roadmap)

### Фаза 0: Инфраструктура и Архитектурный фундамент (Выполнено)

- [x] Закрыть все P0/P1 (безопасность, CSRF, RCE, path traversal, SSRF).
- [x] Удалить визуальные редакторы для Страниц и Блоков (защита от RCE).
- [x] Внедрить Правило 3 файлов (`.php`, `.rules.json`, `.editor.json`) для всех сущностей (Страница, Блок, Филлер, Схема, Скин).
- [x] Удалить глобальные манифесты (`blocks.json`, `schemes.json`).
- [x] Создать безопасный AI-фасад (`bin/aicli.php`).
- [x] Внедрить безопасный PageCompiler (генерация страниц из JSON без RCE).
- [x] Реализовать команды `page:write`, `page:read`, `domain:remove`.
- [x] **AI-Слой**: Интеграция локальной модели Qwen 2.5 Coder 7B. Успешная генерация 10-страничного сайта (21 команда за 1 шаг).
- [x] Настроить CI-защиту: тесты + PHPStan + CS-Fixer `--dry-run` (**570 тестов, 1671 assertion, 87 файлов**).
- [x] **Page-rules unify**: Единая модель `.rules.json` (`algoritm_{name}.rules.json` для страниц, `algoritm/algoritm.rules.json` для root). Page-слой (PagePath, PageRules, PageNamespace). `migrate:page-rules`. Severity-aware `structure:check`. Закрыт P0 в aicli (`page:write` проверяет domain по `allowed_domains`).

- [x] **CLI-аудит (37 команд)**:
  - `scheme:copy/scheme:remove/scheme:save` — новые CLI-команды (SchemeController теперь тонкий клиент).
  - `*:list` (6 команд: `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`) — `--json` + `Result::$data`.
  - `content:store/content:remove/content:list` — runtime-хранилище `/content/` (вне webroot).
  - `skin:new/skin:list/skin:edit/skin:remove` — `www/media/<domain>/<allias>/skin/` (git-tracked).
  - Path traversal guard во всех командах с `--domain`/`--name`.
  - realDomain: пути в `app/` строятся по `domains_real`, а не по входному хосту.
  - Карантин (`*_trash_<timestamp>/`) для всех destructive-команд, **6 уровней** (app + content + skin).
  - `trash:clear` — обход **6 уровней** карантина, symlink-safe `Fs::rmrf`, флаги `--force/--older/--domain`.
  - Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
  - Полная очистка конфига: `domain:remove` (16 ключей), `allias:remove` (8 ключей).
  - `filler:toggle` — idempotent, проверяет блок на диске.
  - `db:migrate --dir` — path traversal guard.
  - `admin:passwd` / `user:new` — валидация (login/password/email).
  - `structure:repair` — root через PageCompiler, `algoritm.editor.json`, path traversal guard.
  - `VarsExtractCommand` — удалён (мёртвый код).
  - `Fs::rmrf` — единый рекурсивный метод (Windows-safe, symlink-safe).

- [x] **PHPStan/CS-Fixer расширены на `bin/` и `www/`** (240 файлов).
- [x] **AINET** (библиотека `src/Ainet/`):
  - UrlGuard: SSRF-защита (scheme/port/whitelist/private IP/ручные редиректы).
  - DomainWhitelist: точный матч, без wildcard, fail-safe.
  - HttpClient: GET-only, `CURLOPT_FOLLOWLOCATION=false`, повторный UrlGuard на каждый Location.
  - Cache: `sha256(url)` → file+meta, TTL 7 дней, `Fs::rmrf`.
  - ImageSearch: wikimedia (default), openverse (fallback), автофоллбэк primary → next для default.
  - AinetFacade: `searchImages`/`fetchJson`/`fetchText`/`downloadImage` (bytes в память, без записи).
  - AinetConfig: fail-safe (пустой whitelist = всё запрещено).
  - `bin/ainet.php` — standalone-отладка (не в AI-whitelist).
- [x] **Content** (`/content/`): runtime-хранилище AI-контента, **вне webroot**, `.gitignore`. Отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). Дедупликация по hash. Запись только через `content:store`.
- [x] **Skin** (`www/media/<domain>/<allias>/skin/`): git-tracked инфраструктура. CLI `skin:new/list/edit/remove`. `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import`/`javascript:`.
- [x] **Roles (9) + Navigator (`next[]` HATEOAS)**: `src/Ai/Roles.php`, `src/Ai/Navigator.php`. Роль `*` (default) = union всех ролей + специфичные. Инвариант покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`.
- [x] **Интеграция `net:*` в aicli**: whitelist включает `net:status`, `image:search`, `image:download`, `fetch`. `image:download` → AinetFacade (bytes) → `shell_exec` → `cli.php content:store`. Openverse 301 → `.org`, `default_provider = wikimedia`, `read_timeout 20s`.
- [x] **`*:list`** (6 команд, `--json`, `Result::$data`).
- [x] **Git-гигиена**: untrack `/content/`, `app/damoony3test.ru/`, `config/ainet.config`, `config/agent.config`. `.gitignore` обновлён.
- [x] **E2E-оркестратор**: `agent_test.py` (Ollama ↔ aicli) — рабочий. `ai_agent/` (Planner→Builder→Content) — прототип (P3).

### Фаза 1: AI-Интеграция и Маркетинг (Ближайший месяц)

- [ ] **AI-Слой**: Реализовать механизм транзакций (Rollback) в Python-цикле агента при падении одной из команд.
- [ ] **AI-Слой**: Разбиение длинных задач на батчи (для стабильности Qwen 7B).
- [ ] **AI-Слой**: Цикл редактирования существующих страниц (агент читает `page:read`, анализирует и перезаписывает `page:write`).
- [x] **AICLI**: Расширение whitelist (`scheme:save`, `skin:new/list/edit`, `*:list`) с валидацией аргументов.
- [ ] **AICLI**: `block:edit`, `filler:edit`, `skin:bundle` (3 команды для полного whitelist designer/bundler).
- [ ] **AINET**: Rate limit 30/min применить в AinetFacade (сейчас только в конфиге).
- [ ] **AINET**: POST/PUT/DELETE, парсинг HTML (для скриншотов/документации).
- [ ] **Content**: orphan cleanup (неиспользуемые hash), CDN для больших сайтов.
- [ ] **Skin**: UI-менеджер скинов в админке (CLI уже есть).
- [ ] **AI ↔ AICLI/AINET**: Доработка инструментов взаимодействия (транзакции, батчи, протокол ошибок).
- [ ] **Выбор AI**: Бенчмарк локальных моделей на GPU 8GB (Qwen 7B/14B, Llama, Mistral, DeepSeek). Метрики: время, % валидных JSON, шаги до завершения.
- [ ] **Тестовый прогон**: Формализованный протокол (5 сценариев: 3/10/20 страниц с картинками, редактирование, восстановление после ошибки).
- [ ] **UX**: Взаимодействие пользователь — инструкции — агент — обратная связь (UI в админке: поле ввода, прогресс, результат, инструкции).
- [ ] SEO-модуль: `sitemap.xml`, `robots.txt`, `<meta>` из настроек страницы.
- [ ] Подключение GA / Метрики через `site.config`.
- [ ] UTM-метки в ссылках.
- [ ] Формы + Лиды (отправка на email/Telegram).
- [ ] Профиль «Визитка» и «Корпоратив» в CLI (`site:new --profile=corporate`).

### Фаза 2: E-commerce (2–3 месяца)

- [ ] Модуль «Каталог»: таблица товаров, категории, карточка товара.
- [ ] Модуль «Корзина»: сессии, оформление заказа.
- [ ] Интеграция ЮKassa/Тинькофф (тестовый режим).
- [ ] Админка заказов (список + статусы).
- [ ] Профиль «Магазин» в CLI.

### Фаза 3: Enterprise и Возвращение Безопасного Low-Code (3–6 месяцев)

- [ ] Перенос админки в `app/admin/` (полная изоляция от сайтов).
- [ ] Зеркальные URL в админке (`/admin/about` = `/about`).
- [ ] SPA-режим админки (fetch без перезагрузки).
- [ ] Ролевая модель: admin, editor, manager, viewer.
- [ ] REST API (read-only) для внешних систем.
- [ ] Мультиязычность контента (hreflang, локализация страниц).
- [ ] Профиль «Портал».
- [ ] Создать единый безопасный визуальный редактор для Страниц и Блоков (строгий конструктор блоков, работающий через безопасный JSON, без генерации сырого PHP).

---

## 3. Что изменится в архитектуре после внедрения (v3.x)

- `www/index.php` → сайт + роутинг `/admin` через Host/Path.
- `app/admin/` → изолированная админка (Enterprise).
- `config/admin.config` → отдельный конфиг с доступами к БД для админки.

**Что останется:** API DomainResolver, RegionRegistry, PageHandler, Правило 3 файлов, PageCompiler, Page-слой (PagePath/PageRules/PageNamespace), FileRouter, карантин (**6 уровней**), path traversal guard, AINET (UrlGuard, HttpClient, Cache, ImageSearch, Facade), Content (`/content/`, дедупликация, `www/content.php`), Skin (`www/media/<domain>/<allias>/skin/`, CLI `skin:*`), Roles (9) + Navigator, `Fs::rmrf`.

---

## 4. Что переписать в документации после внедрения

После каждой фазы — обновлять `manifest.md`, `architecture.md`, `contracts.md`, `cli.md`, `guide.md`, `admin.md`, `ai_agent.md`, `agent_architecture.md`, `ainet.md`, `readme.md`, `index.md`, `scenarios.md`, `start_new_agent.md`, `philosophy.md`.

---

## 5. Стратегические решения (что НЕ делать)

- ❌ Маркетплейс плагинов. Противоречит принципу «строгие правила».
- ❌ Свободная вёрстка (WYSIWYG). Мы — конструктор блоков, не Tilda.
- ❌ Голый Headless (без UI). Наш козырь — CLI-First архитектура + AI-Native интеграция.
- ❌ Плагины в стиле WP. Apps как отдельные алиасы — да, плагины в ядре — нет.
- ❌ Выполнение сырого PHP из UI или от AI. Только через безопасный JSON (PageCompiler) или явный флаг `security.allow_php_blocks`.
- ❌ Прямое удаление файлов без карантина. Все destructive-операции — через `*_trash_<timestamp>/` + `--force`.
- ❌ Прямая запись AINET на диск. Только через CLI `content:store` (AINET = сеть, CLI = FS).
- ❌ POST/PUT/DELETE в AINET (только GET). Никаких логинов на сайтах, никаких form-submit.
- ❌ Wildcard в URL whitelist (только точный матч). Никаких subdomain-match, никаких regex.
- ❌ Смешивание Content (`/content/`) и Skin (`www/media/.../skin/`). Разные зоны, разные CLI-команды, разный жизненный цикл.

---

## 6. Как позиционировать проект после внедрения

- **Веб-студии:** «Фабрика сайтов» (10+ доменов в одном инстансе).
- **AI-Агенты:** «Автономная безопасная фабрика сайтов с картинками (генерация за 5 секунд)».
- **Маркетологи:** «Конверсия и SEO за 1 клик».
- **E-commerce:** «Магазин без плагинного ада».
- **Enterprise:** «Headless-безопасность + Low-Code-удобство».

---

## 7. Итоговая оценка

- **Сейчас (Фаза 0 + AINET + Content + Skin + Roles/Navigator)**: Production Ready, AI-Native (CLI-First + AI Generation + SSRF-safe internet + content + skin + 9 ролей) — **9.8/10**.
- **После Фазы 1** (AI + Маркетинг + полный AICLI/AINET): «AI-Конструктор для агентств» — **9.9/10**.
- **После Фазы 2** (E-commerce): «SaaS для бизнеса» — **9.9/10**.
- **После Фазы 3** (Enterprise): «Корпоративная платформа» — **10/10**.

⚠️ **Отметка в документации:** Этот файл — план, а не реальность. При расхождении с текущим кодом — доверяйте `manifest.md`.