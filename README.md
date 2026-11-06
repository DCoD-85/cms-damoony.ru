# Damoony Engine

Лёгкий PHP 8.4 движок для мультидоменных сайтов с архитектурой CLI-First и нативной интеграцией AI-агентов.

**Статус:** v3.1-dev (Multi-Agent Orchestrator + AIUI). Полное состояние — [`_doc/arch.md`](_doc/arch.md). Дорожная карта — [`_doc/v3.1-design.md`](_doc/v3.1-design.md).

---

## Что это

Damoony Engine — это AI-Native CMS. Она сочетает скорость статического сайта (рендеринг за 0.8–3 мс) с мощью современных LLM.

Главные отличия от классических CMS:

1. **CLI-First:** CLI (`bin/cli.php`) — единственный источник истины. Админка и AI-агенты (`bin/aicli.php`) — лишь безопасные обёртки над CLI. Актуальный список команд — `php bin/cli.php help`.
2. **Безопасный Low-Code:** Визуальный Drag&Drop оставлен ТОЛЬКО для Схем. Страницы, Блоки и Меню редактируются через CodeMirror или AI (через безопасный JSON). Это полностью исключает RCE-уязвимости.
3. **AI-Интеграция (E2E):** Встроенный слой `bin/aicli.php` (9 ролей + Navigator) и Python-оркестратор (`ai_agent/brain.py`), который автономно генерирует сайты по текстовому брифу. Архитектура построена на паттерне Map-Reduce (Архитектор → Копирайтеры → Дизайнер).
4. **Безопасный интернет (AINET):** Библиотека `src/Ainet/` (SSRF-защита, whitelist, только GET, кэш 7 дней) позволяет AI скачивать картинки, не имея доступа к локальной сети.
5. **Строгая архитектура:** Правило 3 файлов (`.php`, `.rules.json`, `.editor.json`) для всех сущностей. Карантин на 6 уровней для удаления файлов. Скин алиаса (`www/media/[domain]/[allias]/skin/`, CSS/JS). Wizard-генерация (`brain.py`) пишет два файла: `main.css` (base + layout + center) и `top.css` (меню). См. `arch.md` §3.4.
6. **AIUI (AI User Interface):** два независимых AI-сценария для пользователя:
   - **AIUI** (`/admin/aiui.php`) — встроенный AI-редактор страниц (split-screen: превью + чат). Интервьюер собирает бриф, кнопка «🚀 Создать» запускает `Orchestrator` (Interviewer → Architect → Coder), изменения пишутся в изолированную сессию `.sess_*`. См. [`_doc/aiui.md`](_doc/aiui.md) и [`_doc/orchestrator.md`](_doc/orchestrator.md).
   - **Wizard** (`ai_agent/brain.py`) — фоновый мультиагент (Python) для генерации сайта «с нуля» по брифу. См. [`_doc/ai.md`](_doc/ai.md) §7.
   
   Оба используют безопасный JSON (`PageCompiler`/`BlockCodegen`), AI не пишет raw PHP.

---

## Стек

- **Язык:** PHP >= 8.4 (Composer, PSR-4/3/11).
- **БД:** MariaDB 11+ / MySQL 8+ (через PDO). SQLite (для тестов).
- **Веб-сервер:** Nginx + PHP-FPM или Apache 2.4.
- **AI-Оркестратор:** Python 3.10+ (`ai_agent/brain.py`), Ollama (Gemma 4, Qwen 2.5).
- **Качество:** PHPUnit 10, PHPStan (level 8), PHP-CS-Fixer.

---

## Быстрый старт

Убедитесь, что в `config/engine.config` прописаны доступы к MariaDB.

```bash
git clone https://github.com/DCoD-85/cms-damoony.ru.git damoony
cd damoony
composer install
cp config/ainet.config.example config/ainet.config

# Инициализация БД и администратора
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=[strong_password]

# Создание первого сайта (вручную)
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

**Или запуск AI-генерации сайта (E2E):**

```bash
# Убедитесь, что Ollama запущена
python -m ai_agent.brain damoony3test.ru coffee "Сделай лендинг IT-агентства из 3 страниц" gemma4:e4b
```

Откройте `http://[domain]/` (сайт) и `http://[domain]/admin/` (админка).

---

## Документация

👉 **Новичкам и аудиторам начинать здесь:** [`_doc/start_new_audit.md`](_doc/start_new_audit.md) — окружение, правила работы, карта проекта, read-order.

**Остальные документы в `_doc/`:**

| Файл | О чём |
|---|---|
| [`arch.md`](_doc/arch.md) | Архитектура, 10 принципов, инварианты, открытые P2 |
| [`cli.md`](_doc/cli.md) | Команды CLI: флаги, идемпотентность, guards |
| [`dev.md`](_doc/dev.md) | How-to: страница, блок, схема, скин + Python smoke tests |
| [`ai.md`](_doc/ai.md) | aicli (HATEOAS, роли, Navigator), AINET (SSRF), мультиагент, AI Wizard (Web UI) |
| [`admin.md`](_doc/admin.md) | UI админки + делегирование CLI |
| [`install.md`](_doc/install.md) | Установка с нуля, конфиги, веб-сервер |
| [`roadmap.md`](_doc/roadmap.md) | Планы на v3.0.0 (AI-Fullstack Factory) |

**Правило:** один факт — одно место. Метрики, перечень команд, требования — не дублируются, только ссылки.