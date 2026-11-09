from pathlib import Path
import subprocess

router_path = Path("www/router.php")
text = router_path.read_text(encoding="utf-8")

# Проверяем, что там еще нет strict_types
if "declare(strict_types=1);" not in text:
    # Вставляем после открывающего тега <?php
    text = text.replace("<?php\n", "<?php\n\ndeclare(strict_types=1);\n", 1)
    router_path.write_text(text, encoding="utf-8")
    print("✅ Patched: www/router.php (added declare(strict_types=1))")
else:
    print("ℹ️ router.php already has strict_types")

# Проверка синтаксиса
php_bin = "E:/localserver/tools/php8.5.12/php.exe"
ini_path = "E:/localserver/tools/php8.5.12/php.ini"
res = subprocess.run([php_bin, "-c", ini_path, "-l", str(router_path)], capture_output=True, text=True)
print("Syntax OK" if res.returncode == 0 else f"Syntax Error:\n{res.stderr}")