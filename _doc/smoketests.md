# Smoke tests (Python)

Не-CI смоук-тесты для Python-слоя `ai_agent/brain.py`. Проверяют **чистые функции** без LLM, без Ollama, без CLI, без БД.

Запускать вручную: перед E2E `python -m ai_agent.brain ...` и после любой правки `ai_agent/brain.py`.

**Не входят** в `composer test` (там только PHPUnit).

---

## Расположение

| Файл | Назначение |
|---|---|
| `_dev/smoketests/test_brain_safety.py` | 23 теста, встроенный плейн-раннер |
| `_dev/smoketests/requirements.txt` | `pytest>=7.0` (опционально) |
| `_doc/smoketests.md` | Этот файл |

---

## Запуск

### Без зависимостей (рекомендуется)

```bash
python _dev/smoketests/test_brain_safety.py
```

Ожидаемый вывод:

```
  ✓ test_safe_href_anchor
  ✓ test_safe_href_data_rejected
  ...
  ✓ test_validate_plan_topo_sort

23 passed, 0 failed
```

Работает на любом Python 3.10+ без `pip install`.

### С pytest (опционально, для `-k`, `-v`)

**Сначала убедись, что `python` и `pytest` — один интерпретатор.**

```bash
python -c "import sys; print(sys.executable)"
python -m pytest --version
```

Если `No module named pytest` — у тебя два Python (MSYS2 + Windows). Не смешивай:
`pip install --user` под Windows кладёт пакеты в `C:\Users\<u>\AppData\Roaming\Python\...`, а `python` в GitBash — из MSYS2 (`C:/msys64/...`). Три варианта:

```bash
# A. Поставить в тот python, что в PATH (MSYS2)
python -m pip install --user pytest

# B. Использовать Windows python
py -3 -m pip install --user pytest
py -3 -m pytest _dev/smoketests/ -v

# C. Плейн-раннер (без pytest) — полностью покрывает
python _dev/smoketests/test_brain_safety.py
```

---

## Покрытие

### `_safe_href(url)` — валидация URL перед `<a href>`

| Кейс | Ожидание |
|---|---|
| `/about`, `#anchor`, `https://...`, `http://...` | без изменений |
| `javascript:`, `data:`, `vbscript:`, `file:`, `//evil` | `None` |
| `/foo" onclick="x` | `/foo%22 onclick=%22x` |
| `/foo<script>` | `/foo%3Cscript%3E` |

Закрывает XSS через `[LINK: ...]`: `PageCompiler`/`BlockCompiler` используют
`Text::raw(strip_tags($s, '<a><strong>'))` — тег `<a>` выживает, но href
санитизирован на стороне Python.

### `_sanitize_name(name)` — санитизация имени страницы

| Вход | Выход |
|---|---|
| `About` / `About Us` / `Care-Guide!` | `about` / `aboutus` / `careguide` |
| `__root__` | `__root__` |
| `123page` | `page_123page` |
| `''` | `page` |

Закрывает path traversal в имени до CLI.

### `validate_plan(plan)` — детерминированная правка плана LLM

- topo sort (parent перед child)
- drop orphan parent → `None`
- split склеенных имён: `care_guide_documentation` → `documentation`
- sanitize parent: `Parent With Spaces` → orphan → `None`
- cycle guard (A↔B не вешает)

---

## Что НЕ покрыто (требует интеграции)

- `preflight` — нужен `bin/aicli.php` + `config/agent.config`
- `fetch_image` — нужен AINET + whitelist
- `run_multi_agent` — полный пайплайн с Ollama

Для этого — E2E на тестовом домене:

```bash
python -m ai_agent.brain damoony3wizard.ru www "Тестовый бриф" qwen2.5-coder:7b
```

---

## Связанные документы

- `_doc/dev.md`, `_doc/ai.md`, `_doc/arch.md`