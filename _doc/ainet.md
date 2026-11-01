--- НАЧАЛО ДОКУМЕНТА ---

AINET — безопасный выход AI-агента в интернет
Библиотека `src/Ainet/*` для интеграции локальных LLM (Ollama) с реальным интернетом. **Не является отдельным фасадом для AI.** Единственная точка входа — `bin/aicli.php`. Зеркалит архитектуру AICLI: whitelist, жёсткая валидация, no RCE, no SSRF. AINET отвечает только за сеть; запись на диск — через CLI.

Статус: в разработке (Приоритет 3).
Дата дизайна: 1 ноября 2026.
Связанные документы: ai_agent.md, architecture.md, contracts.md, manifest.md.

---

1. Зачем

Локальная модель (Qwen 2.5 Coder 7B / 14B через Ollama) не имеет встроенного доступа в интернет. Без AINET:
- Агент генерирует только текст и разметку (h1, text, ul)
- `<img>` — только внешние URL (unsplash.com/photo-...), которые могут отвалиться
- Нет актуальных данных (курсы, погода, документация)
- Нет возможности скачать/сохранить картинку локально

С AINET:
- Поиск картинок по теме (Openverse, Unsplash, Pexels)
- Скачивание в `www/media/<domain>/<allias>/` с метаданными (размер, mime, hash)
- Fetch JSON-API (Wikipedia, GitHub, публичные API)
- Fetch текст (Wikipedia, документация)
- Кэш → экономия API-квот, ускорение повторных генераций

Цель: превратить агента из «генератора текста» в «генератора сайтов с реальным контентом», не давая ему прямого доступа к сети или файловой системе.

---

2. Архитектура (вариант A)

AINET — **библиотека** (`src/Ainet/*`), подключаемая внутри AICLI. Не отдельный фасад. Единственная точка входа для AI — `bin/aicli.php`.

```
[AI-Модель (Ollama, Qwen)]
      ↕ (JSON-план)
[Python Скрипт (Agentic Loop)]
      ↕ (JSON через STDIN)
[bin/aicli.php (Единственный AI-фасад)]
      │
      ├── app/* команды (page:*, domain:*, allias:*, scheme:*, block:*, filler:*, structure:*, migrate:*, trash:*)
      │       └── shell_exec → bin/cli.php → FS (app/)
      │
      ├── media:* команды (media:store, media:remove, media:list)
      │       └── shell_exec → bin/cli.php → FS (www/media/)
      │
      └── net:* команды (net:status, fetch, image:search, image:download)
              ├── fetch, image:search, net:status → src/Ainet/* (in-process) → Интернет
              └── image:download → src/Ainet/HttpClient (bytes в память)
                                    └── shell_exec → bin/cli.php media:store → FS (www/media/)
```

Разделение ответственности:

| Слой | Что делает | Что НЕ делает |
|---|---|---|
| **bin/aicli.php** | Единственная точка входа для AI. Whitelist, валидация, диспетчеризация. | Не содержит бизнес-логику AINET/CLI. Не пишет на диск. |
| **src/Ainet/** | Сеть: SSRF-защита, whitelist URL, HTTP GET, кэш, парсинг провайдеров. | **Не пишет на диск.** Не создаёт папки. Не удаляет файлы. |
| **bin/cli.php** | FS: единственный источник истины для записи/удаления/списка. Все media:* операции. | Не ходит в сеть. |
| **bin/ainet.php** | Standalone-отладка (`echo '{...}' | php bin/ainet.php`). **Не вызывается AI.** Не в whitelist. | — |

Структура библиотеки:
```
src/Ainet/
├── UrlGuard.php           — SSRF-защита (private IP, scheme, port, user/pass)
├── UrlGuardException.php  — типизированное исключение с hint
├── DomainWhitelist.php    — whitelist из config/ainet.config
├── HttpClient.php         — GET с таймаутами/limits (curl), ручные редиректы
├── Cache.php              — sha256 URL → file + meta, TTL 7 дней
├── AinetFacade.php        — публичный API для AICLI:
│                             searchImages(), fetchJson(), fetchText(),
│                             downloadImage() → возвращает bytes + meta (без записи)
└── ImageSearch/
    ├── ProviderInterface.php
    ├── OpenverseProvider.php   (default, без ключа)
    ├── UnsplashProvider.php    (опционально, с ключом)
    ├── PexelsProvider.php      (опционально, с ключом)
    └── WikimediaProvider.php   (fallback, без ключа)
```

Ключевое: **AINET не пишет на диск.** `AinetFacade::downloadImage()` возвращает `{bytes, mime, width, height, hash, meta}`. Запись в `www/media/<domain>/<allias>/<category>/<hash>.<ext>` — через `cli.php media:store`, который вызывается из `aicli.php` через `shell_exec`.

---

3. Команды (whitelist AICLI)

Все команды вызываются **через `bin/aicli.php`** (единственная точка входа). Поле `facade` не нужно — AICLI сам роутит по префиксу:
- `page:*`, `domain:*`, `allias:*`, `scheme:*`, `block:*`, `filler:*`, `structure:*`, `migrate:*`, `trash:*` → `bin/cli.php` (shell_exec)
- `media:*` → `bin/cli.php` (shell_exec)
- `net:status`, `fetch`, `image:search` → `src/Ainet/AinetFacade` (in-process)
- `image:download` → `AinetFacade::downloadImage()` (bytes) → `cli.php media:store` (shell_exec)

`bin/ainet.php` — не для AI, только standalone-отладка.

3.1. net:status
Назначение: диагностика — whitelist, кэш, rate limit, провайдеры.

```json
{"command": "net:status", "args": {}}
```
Ответ:
```json
{
  "ok": true,
  "data": {
    "providers": ["openverse", "wikimedia"],
    "whitelist_count": 15,
    "cache_size_mb": 12.4,
    "cache_entries": 34,
    "rate_limit": {"used": 3, "max": 30, "window": "1m"}
  }
}
```

3.2. image:search
Назначение: поиск картинок по запросу.

```json
{
  "command": "image:search",
  "args": {
    "query": "coffee cup",
    "count": 5,
    "provider": "openverse"
  }
}
```
Args:
- `query` (string, обязательный, 1–100 символов)
- `count` (int, 1–20, по умолчанию 5)
- `provider` (string, optional, по умолчанию из config: openverse)

Ответ:
```json
{
  "ok": true,
  "data": {
    "provider": "openverse",
    "query": "coffee cup",
    "results": [
      {
        "url": "https://.../image1.jpg",
        "preview_url": "https://.../thumb1.jpg",
        "width": 1200,
        "height": 800,
        "license": "cc0",
        "author": "...",
        "source": "openverse"
      }
    ]
  }
}
```

3.3. image:download
Назначение: скачать картинку и сохранить в `www/media/<domain>/<allias>/<category>/`.

Цепочка: `aicli.php` → `AinetFacade::downloadImage()` → `cli.php media:store` → FS.

```json
{
  "command": "image:download",
  "args": {
    "url": "https://.../image1.jpg",
    "domain": "example.ru",
    "allias": "www",
    "category": "coffee",
    "alt": "Coffee cup on table"
  }
}
```
Args:
- `url` (string, обязательный, must be в whitelist)
- `domain` (string, обязательный, must быть в `config/agent.config.allowed_domains`)
- `allias` (string, обязательный, `[a-z][a-z0-9_]*`, mirror структуры `app/<domain>/<allias>/`)
- `category` (string, optional, `[a-z0-9_-]{1,32}`, по умолчанию "general")
- `alt` (string, optional, для metadata)

Ответ:
```json
{
  "ok": true,
  "data": {
    "local_url": "/media/example.ru/www/coffee/a3f5c1e9.jpg",
    "path": "www/media/example.ru/www/coffee/a3f5c1e9.jpg",
    "size": 45210,
    "mime": "image/jpeg",
    "width": 1200,
    "height": 800,
    "hash": "a3f5c1e9...",
    "cached": false
  }
}
```

3.4. fetch
Назначение: GET-запрос к whitelisted URL, вернуть JSON или текст.

```json
{
  "command": "fetch",
  "args": {
    "url": "https://ru.wikipedia.org/api/rest_v1/page/summary/Кофе",
    "type": "json"
  }
}
```
Args:
- `url` (string, обязательный, must be в whitelist)
- `type` (string, `json` | `text`, по умолчанию `json`)

Ответ для json:
```json
{"ok": true, "data": {"json": {...}, "status": 200, "cached": false}}
```

Ответ для text:
```json
{"ok": true, "data": {"text": "...", "status": 200, "size": 45210, "cached": false}}
```

3.5. media:store (CLI, новая команда — 25-я)
Назначение: запись байтов в `www/media/`. **Вызывается только AINET (через AICLI).** AI напрямую не должна вызывать (в whitelist AICLI для AI — запрещена, только внутренний вызов).

```bash
php bin/cli.php media:store --domain=example.ru --allias=www --category=coffee --data_b64=<base64> --mime=image/jpeg --alt="Coffee cup"
```

Args:
- `domain`, `allias` — из `config/agent.config.allowed_domains` + regex
- `category` — `[a-z0-9_-]{1,32}`
- `data_b64` — base64 содержимого (bytes)
- `mime` — `image/jpeg|png|gif|webp|svg+xml`
- `alt` — опционально

Ответ:
```json
{
  "ok": true,
  "data": {
    "local_url": "/media/example.ru/www/coffee/a3f5c1e9.jpg",
    "path": "www/media/example.ru/www/coffee/a3f5c1e9.jpg",
    "hash": "a3f5c1e9...",
    "size": 45210,
    "deduplicated": false
  }
}
```

3.6. media:remove (CLI, новая команда — 26-я)
Назначение: удаление медиа в карантин. Доступна через CLI вручную (не для AI).

```bash
php bin/cli.php media:remove --domain=example.ru --allias=www --category=coffee --hash=a3f5c1e9 --force
```
Перемещает `www/media/<domain>/<allias>/<category>/<hash>.*` в `www/media/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/`.
Очистка — `trash:clear` (расширить на media).

3.7. media:list (CLI, новая команда — 27-я)
Назначение: список медиа домена/алиаса. Для отладки и админки.

```bash
php bin/cli.php media:list --domain=example.ru [--allias=www] [--category=coffee]
```
Ответ: JSON-список `{path, size, mime, source_url, downloaded_at}`.

---

4. Безопасность (SSRF-защита)

4.1. Whitelist доменов
Только домены из `config/ainet.config.allowed_domains`. Wildcard не поддерживается (`*.example.com` запрещён — только точные домены).

Дефолтный whitelist:
```json
[
  "api.openverse.engineering",
  "api.unsplash.com",
  "images.unsplash.com",
  "api.pexels.com",
  "images.pexels.com",
  "commons.wikimedia.org",
  "upload.wikimedia.org",
  "ru.wikipedia.org",
  "en.wikipedia.org",
  "api.github.com"
]
```

4.2. Только GET
POST/PUT/DELETE/PATCH — запрещены. Внутри `HttpClient` жёстко: `CURLOPT_HTTPGET = true`.

4.3. Блок приватных IP
Резолвим hostname → IP. Если IP в private range — отказ.
- 127.0.0.0/8
- 10.0.0.0/8
- 172.16.0.0/12
- 192.168.0.0/16
- 169.254.0.0/16 (link-local, AWS metadata)
- ::1 (IPv6 localhost)
- fc00::/7 (IPv6 unique local)
- fe80::/10 (IPv6 link-local)

4.4. Запрет scheme
Только `http://` и `https://`. `file://`, `ftp://`, `gopher://`, `data:`, `javascript:` — отказ.

4.5. Запрет портов
Только 80 и 443. Остальные (22, 3306, 6379, 11211, 9200) — отказ.

4.6. Лимиты
- `timeout_connect`: 5s
- `timeout_read`: 10s
- `max_size`: 10 MB
- `max_redirects`: 3
- `rate_limit`: 30 req/min (token bucket)
- `cache_ttl_days`: 7

4.7. Валидация URL
- Parse `parse_url()` → обязателен
- `scheme` ∈ {http, https}
- `host` — непустой, в whitelist
- `port` — пустой или 80/443
- `user`/`pass` — запрещены (`http://user:pass@...` — SSRF через auth)
- Fragment `#...` — отрезается
- Query string — разрешён (для API), но валидируется на длину (max 2048)

4.8. Ручные редиректы
`CURLOPT_FOLLOWLOCATION = false`. Каждый редирект (Location) → повторная проверка `UrlGuard::validate()`. Иначе: `http://whitelisted.com/redirect?to=http://127.0.0.1/` — обход.

4.9. Защита от обхода
- `http://evil.com/../../etc/passwd` — не сработает (это HTTP, не file)
- `http://evil.com@127.0.0.1/` — user=evil.com, host=127.0.0.1 → SSRF → отказ (private IP)
- `http://127.0.0.1:80@evil.com/` — host=evil.com → пропустится whitelist → ок (это публичный запрос)
- `http://evil.com#@127.0.0.1/` — fragment отрезается → host=evil.com → ок
- `http://api.github.com/redirect?to=http://127.0.0.1/` — Location-редирект → повторный UrlGuard → отказ

---

5. Кэш

Назначение: экономия API-квот, ускорение повторных генераций, работа офлайн.

Структура:
```
var/ainet-cache/
├── <sha256(url)>/
│   ├── body              — сырое тело ответа
│   └── meta.json         — {url, mime, size, status, timestamp, headers}
```

TTL: 7 дней (настраивается). После — повторный запрос.

Инвалидация: `net:cache:clear` (не в whitelist AI, только вручную через CLI).

Ответ помечается `cached: true/false`.

---

6. Хранение медиа (структура зеркалит app/)

Путь: `www/media/<domain>/<allias>/<category>/<hash>.<ext>`
- `<domain>` — из `config/agent.config.allowed_domains` (проверка)
- `<allias>` — зеркалит `app/<domain>/<allias>/`, regex `[a-z][a-z0-9_]*`
- `<category>` — от AI, `[a-z0-9_-]{1,32}`
- `<hash>` — sha256 содержимого (первые 16 символов)
- `<ext>` — по `Content-Type`: jpg, png, gif, webp, svg

Пример соответствия:
```
app/example.ru/www/algoritm/about/...       → www/media/example.ru/www/about/...
app/example.ru/doc/algoritm/...             → www/media/example.ru/doc/...
```

Три зоны `www/media/` (не смешиваются):
```
www/media/
├── admin/                          # инфраструктура (git, руками)
├── shared/                         # инфраструктура (git, руками)
├── .trash/                         # карантин медиа (runtime)
├── <domain>/skin/                  # скины (git, руками)
└── <domain>/<allias>/<category>/   # AI-контент (runtime, .gitignore)
    ├── <hash>.jpg
    └── <hash>.meta.json
```

`www/media/<domain>/<allias>/` — **только** через `cli.php media:*`. Скины и admin — отдельно.

Метаданные: `www/media/<domain>/<allias>/<category>/<hash>.meta.json`
```json
{
  "source_url": "https://...",
  "alt": "Coffee cup",
  "size": 45210,
  "mime": "image/jpeg",
  "width": 1200,
  "height": 800,
  "downloaded_at": "2026-11-01T12:00:00Z",
  "license": "cc0",
  "author": "..."
}
```

Дедупликация: если хэш уже есть — возвращаем существующий путь без скачивания.

Валидация (в `media:store`):
- MIME ∈ {image/jpeg, image/png, image/gif, image/webp, image/svg+xml}
- SVG — сохраняем, но помечаем `unsafe_for_inline` (потенциальный XSS через `<script>` в SVG)
- Размер ≤ 10 MB
- Разрешение ≥ 200×200 (защита от трекеров 1×1)

---

7. Провайдеры

7.1. Openverse (default, без ключа)
- Endpoint: `https://api.openverse.engineering/v1/images/`
- Query: `?q=<query>&page_size=<count>`
- Лицензии: CC0, CC-BY, CC-BY-SA, PD
- Автор, source — в ответе
- Плюс: без ключа, много картинок, лицензии чистые
- Минус: качество ниже, чем у Unsplash

7.2. Unsplash (опционально, ключ)
- Endpoint: `https://api.unsplash.com/search/photos`
- Header: `Authorization: Client-ID <key>`
- Free tier: 50 req/hour (demo), 5000 req/hour (production, по заявке)
- Плюс: профессиональные фото
- Минус: нужен ключ, лицензия Unsplash (не CC)

7.3. Pexels (опционально, ключ)
- Endpoint: `https://api.pexels.com/v1/search`
- Header: `Authorization: <key>`
- Free tier: 200 req/hour, 20 000 req/month
- Плюс: качество, лицензия Pexels (бесплатно, attribution)
- Минус: нужен ключ

7.4. Wikimedia Commons (fallback, без ключа)
- Endpoint: `https://commons.wikimedia.org/w/api.php?action=query&...`
- Используется, если Openverse вернул 0 или ошибку
- Лицензии: CC, PD

Приоритет: `provider` из config (по умолчанию `openverse`). При 0 результатов — fallback на Wikimedia Commons.

---

8. Конфиг

`config/ainet.config` (не коммитится, .gitignore):
```json
{
  "allowed_domains": [
    "api.openverse.engineering",
    "api.unsplash.com",
    "images.unsplash.com",
    "api.pexels.com",
    "images.pexels.com",
    "commons.wikimedia.org",
    "upload.wikimedia.org",
    "ru.wikipedia.org",
    "en.wikipedia.org",
    "api.github.com"
  ],
  "providers": {
    "default": "openverse",
    "keys": {
      "unsplash": "",
      "pexels": ""
    }
  },
  "timeouts": {"connect": 5, "read": 10},
  "limits": {
    "max_size": 10485760,
    "max_redirects": 3,
    "rate_per_min": 30,
    "min_image_dimension": 200
  },
  "cache_ttl_days": 7,
  "block_private_ips": true,
  "allowed_schemes": ["http", "https"],
  "allowed_ports": [80, 443],
  "allowed_mimes": [
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
    "image/svg+xml"
  ]
}
```

`config/ainet.config.example` — коммитится, с пустыми ключами.

`.gitignore` — расширить:
```
# AI media (генерируется через media:store, runtime)
/www/media/*/*/
!/www/media/*/skin/
!/www/media/admin/
!/www/media/shared/
/www/media/.trash/
```

---

9. Формат ответа (единый)

Успех:
```json
{"ok": true, "data": {...}}
```

Ошибка:
```json
{
  "ok": false,
  "error": "Domain not in whitelist",
  "hint": "Add 'evil.com' to config/ainet.config allowed_domains",
  "context": {"url": "https://evil.com/x", "host": "evil.com"}
}
```

Поля:
- `error` — короткое описание
- `hint` — что делать AI (для self-correction)
- `context` — детали (url, host, status)

Примеры ошибок:
- `Unknown command 'X'` + hint «Allowed: net:status, image:search, image:download, fetch»
- `Domain 'X' not in whitelist` + hint «Add to config/ainet.config»
- `Private IP blocked` + hint «SSRF protection, cannot fetch internal resources»
- `Blocked scheme 'file'` + hint «Only http, https allowed»
- `Blocked port 22` + hint «Only 80, 443 allowed»
- `Max size exceeded (12 MB > 10 MB)` + hint «Try a smaller file or increase limits.max_size»
- `Rate limit exceeded` + hint «Wait N seconds»

---

10. Что НЕ делает AINET

❌ POST/PUT/DELETE — только GET.
❌ Логин/пароли на сайтах.
❌ Скриншоты сайтов (headless browser).
❌ Парсинг HTML (только JSON API и текст).
❌ Скачивание файлов не-image (PDF, ZIP) — только image.
❌ Работа без config (fail-safe: пустой whitelist → всё заблокировано).
❌ Обход whitelist (wildcard, subdomain match — запрещены).
❌ **Прямая запись на диск** — только через `cli.php media:store`.
❌ **Создание/удаление/список файлов в `www/media/`** — только через `cli.php media:*`.
❌ **Автоматические редиректы** — только ручные с повторной UrlGuard-проверкой.

---

11. Порядок разработки

1. `_doc/ainet.md` — этот документ (готово).
2. `config/ainet.config.example` — шаблон (готово).
3. `src/Ainet/UrlGuardException.php` + `UrlGuard.php` — SSRF (готово, требует фикс: scheme до host, private IP до whitelist).
4. `src/Ainet/DomainWhitelist.php` — whitelist (готово).
5. `src/Ainet/HttpClient.php` — curl GET-only, таймауты, ручные редиректы (повторный UrlGuard).
6. `src/Ainet/Cache.php` — sha256 URL → file + meta.
7. `src/Ainet/AinetFacade.php` — публичный API для AICLI: `searchImages()`, `fetchJson()`, `fetchText()`, `downloadImage()` (bytes в память).
8. `src/Ainet/ImageSearch/ProviderInterface.php` + `OpenverseProvider.php` (+ `WikimediaProvider.php` fallback).
9. `src/Cli/Commands/MediaStoreCommand.php` — запись в `www/media/` (25-я CLI-команда).
10. `src/Cli/Commands/MediaRemoveCommand.php` — в карантин (26-я).
11. `src/Cli/Commands/MediaListCommand.php` — список (27-я).
12. `bin/aicli.php` — расширить whitelist (`net:*`), диспетчеризация на `AinetFacade`.
13. `bin/ainet.php` — standalone-фасад для отладки.
14. Тесты: UrlGuardTest, DomainWhitelistTest, HttpClientTest, CacheTest, MediaStoreCommandTest.
15. Документация: ai_agent.md, architecture.md, contracts.md, cli.md (25-27 команд).

Оценка: 5–6 дней.

---

12. Интеграция с AICLI

**Единственная точка входа — `bin/aicli.php`.** AI не знает про AINET.

Пример JSON-плана от LLM (один вызов aicli.php — один ответ):

```json
[
  {"command": "image:search", "args": {"query": "coffee cup", "count": 3}},
  {"command": "image:download", "args": {"url": "...", "domain": "example.ru", "allias": "www", "category": "coffee"}},
  {"command": "page:write", "args": {"domain": "example.ru", "allias": "www", "name": "about", "blocks": [...]}}
]
```

Внутри `bin/aicli.php`:
- `image:search` → `AinetFacade::searchImages()` (in-process, сеть)
- `image:download` → `AinetFacade::downloadImage()` (bytes) → `cli.php media:store` (shell_exec, FS)
- `page:write` → `cli.php` (shell_exec, FS)

Транзакции (rollback при падении) — задача AICLI 2.0 (Приоритет 2). Пока — sequential, ошибки возвращаются в `{ok: false, error, hint, context}`.

`bin/ainet.php` — **не для AI**, только standalone-отладка:
```bash
echo '{"command": "image:search", "args": {"query": "coffee", "count": 3}}' | php bin/ainet.php
```

---

13. Метрики успеха

- Агент может сгенерировать сайт с картинками (≥1 картинка на страницу) без внешних API-ключей.
- Скачанные картинки хранятся локально (`www/media/<domain>/<allias>/<category>/`), URL стабильны.
- Whitelist покрывает 90% типовых запросов (картинки + Wikipedia + GitHub API).
- Rate limit не превышается при 10-страничном сайте.
- Кэш попадает в ≥50% повторных запросов при итерациях.
- AI делает сайт с картинками через **один фасад** (`aicli.php`), без прямого знания о AINET.
- Ноль прямых записей в `www/media/` из AINET (только через `cli.php media:store`).
- Ноль дублирования валидации domain: `agent.config` (для AI) + `cli.php media:store` (для FS) — одна проверка в CLI.

--- КОНЕЦ ДОКУМЕНТА ---