from pathlib import Path

# =====================================================================
# 1. Обновляем README.md
# =====================================================================
p_readme = Path("README.md")
text_readme = p_readme.read_text(encoding="utf-8")

# Фиксим версию PHP
text_readme = text_readme.replace("Лёгкий PHP 8.4", "Лёгкий PHP 8.5")
text_readme = text_readme.replace("- **Язык:** PHP >= 8.4", "- **Язык:** PHP >= 8.5")

# Фиксим версию PHPUnit
text_readme = text_readme.replace("- **Качество:** PHPUnit 10", "- **Качество:** PHPUnit 11")

# Фиксим удаленный roadmap.md
text_readme = text_readme.replace("| [`roadmap.md`](_doc/roadmap.md) | Планы на v3.0.0 (AI-Fullstack Factory) |", "| [`dbcli.md`](_doc/dbcli.md) | Декларативная работа с БД (JSON-схемы) |\n| [`orchestrator.md`](_doc/orchestrator.md) | Внутренний AI-конвейер (Blackboard) |")

# Добавляем пункт про dbcli в Главные отличия
old_standards = "5. **Строгая архитектура:** Правило 3 файлов (`.php`, `.rules.json`, `.editor.json`) для всех сущностей."
new_standards = "5. **Визуальный конструктор БД (dbcli):** Создание таблиц и миграций прямо из админки. Под капотом — JSON-схемы, из которых генерируются SQL и PHP-провайдеры. Никакого сырого SQL.\n6. **Строгая архитектура:** Правило 3 файлов (`.php`, `.rules.json`, `.editor.json`) для всех сущностей."
text_readme = text_readme.replace(old_standards, new_standards)

# Сдвигаем нумерацию (было 6 -> стало 7)
text_readme = text_readme.replace("6. **AIUI (AI User Interface):**", "7. **AIUI (AI User Interface):**")

p_readme.write_text(text_readme, encoding="utf-8")
print("✅ Patched: README.md")

# =====================================================================
# 2. Обновляем install.md
# =====================================================================
p_install = Path("_doc/install.md")
text_install = p_install.read_text(encoding="utf-8")

# Фиксим старые названия файлов
text_install = text_install.replace("читайте `DEV.md`, для управления — `CLI.md`.", "читайте `_doc/dev.md`, для управления — `_doc/cli.md`.")

# Обновляем веб-сервер (убираем Apache из основных)
text_install = text_install.replace("- **Apache 2.4** + `mod_rewrite` или **Nginx** + PHP-FPM.", "- **Nginx** + PHP-FPM (рекомендуется).")

p_install.write_text(text_install, encoding="utf-8")
print("✅ Patched: _doc/install.md")

# =====================================================================
# 3. Обновляем cli.md (Preamble)
# =====================================================================
p_cli = Path("_doc/cli.md")
text_cli = p_cli.read_text(encoding="utf-8")

# Добавляем упоминание dbcli в самое начало
old_preamble = "Точка входа: `php bin/cli.php [command] [--key=value]`. Возвращает `Result` (код 0 при успехе, 1 при ошибке). Полный список команд — `php bin/cli.php help` (54 команды)."
new_preamble = "Точка входа: `php bin/cli.php [command] [--key=value]`. Возвращает `Result` (код 0 при успехе, 1 при ошибке). Полный список команд — `php bin/cli.php help` (54 команды). \n\n⚠️ **Смежные CLI:** Для работы с БД используйте `bin/dbcli.php` (см. §13), для AI-агентов — `bin/aicli.php`."
text_cli = text_cli.replace(old_preamble, new_preamble)

p_cli.write_text(text_cli, encoding="utf-8")
print("✅ Patched: _doc/cli.md")