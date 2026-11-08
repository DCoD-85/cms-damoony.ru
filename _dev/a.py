import subprocess
from pathlib import Path

# Карта замен (используем raw-строки r"...", чтобы Python не ломал слэши)
replacements = {
    r"Damoony\App": r"Damoony\Core\App",
    r"Damoony\Domain": r"Damoony\Core\Domain",
    r"Damoony\Error": r"Damoony\Core\Error",
    r"Damoony\Files": r"Damoony\Core\Files",
    r"Damoony\Http": r"Damoony\Core\Http",
    r"Damoony\I18n": r"Damoony\Core\I18n",
    r"Damoony\Routing": r"Damoony\Core\Routing",
    r"Damoony\Security": r"Damoony\Core\Security",
    r"Damoony\Session": r"Damoony\Core\Session",
    r"Damoony\Content": r"Damoony\Core\Content",
    r"Damoony\Support": r"Damoony\Core\Support",

    r"Damoony\Algoritm": r"Damoony\UI\Algoritm",
    r"Damoony\Page": r"Damoony\UI\Page",
    r"Damoony\Region": r"Damoony\UI\Region",
    r"Damoony\Response": r"Damoony\UI\Response",
    r"Damoony\Scheme": r"Damoony\UI\Scheme",
    r"Damoony\Skin": r"Damoony\UI\Skin",
    r"Damoony\Ui": r"Damoony\UI",

    r"Damoony\Db": r"Damoony\Data\Db",
    r"Damoony\Auth": r"Damoony\Data\Auth",

    r"Damoony\Ai\Aicli": r"Damoony\Cli\Ai",
}

print("Fixing namespaces in all PHP files...")

# Сканируем ВСЕ php-файлы в проекте (src, bin, www, app)
for file in Path(".").rglob("*.php"):
    if "vendor" in file.parts or "_dev" in file.parts:
        continue

    try:
        text = file.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        continue

    orig = text

    for old_ns, new_ns in replacements.items():
        # Заменяем namespace и use-строки
        text = text.replace(old_ns, new_ns)

    if text != orig:
        file.write_text(text, encoding="utf-8")

print("All namespaces updated!")
print("Running composer dump-autoload...")

# Обновляем карту автозагрузки
subprocess.run(["php", "composer.phar", "dump-autoload"], check=True)
print("Done! Now run 'php composer.phar check'")