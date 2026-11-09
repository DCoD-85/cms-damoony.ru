from pathlib import Path
import subprocess

# 1. Обновляем тест под новые URL
test_path = Path("_dev/tests/Integration/LinkTraversalTest.php")
text = test_path.read_text(encoding="utf-8")

# Меняем страницу логина
text = text.replace("$loginPage = $this->request('/admin/?p=login');", "$loginPage = $this->request('/admin/login');")
text = text.replace("$this->request('/admin/?p=login', 'POST', $postData);", "$this->request('/admin/login', 'POST', $postData);")

# Меняем стартовые точки краулера
text = text.replace("$queue = ['/admin/?p=dashboard', '/admin/?p=domain'];", "$queue = ['/admin/ru/dashboard', '/admin/ru/domains', '/admin/ru/domains/create'];")

# Оставляем требование минимум 3 страниц
test_path.write_text(text, encoding="utf-8")
print("✅ Patched: LinkTraversalTest.php (updated URLs)")

# 2. Создаем роутер для встроенного PHP-сервера в CI
router_path = Path("www/router.php")
router_php = """<?php

// Этот файл используется ТОЛЬКО для встроенного PHP-сервера в тестах CI
// (php -S 127.0.0.1:8080 -t www/ www/router.php)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Админка
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

// 2. Контент
if (str_starts_with($uri, '/content')) {
    require __DIR__ . '/content.php';
    return true;
}

// 3. Статические файлы
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// 4. Публичный сайт
require __DIR__ . '/index.php';
return true;
"""
router_path.write_text(router_php, encoding="utf-8")
print("✅ Created: www/router.php")

# 3. Обновляем CI, чтобы он использовал роутер
ci_path = Path(".github/workflows/ci.yml")
ci_text = ci_path.read_text(encoding="utf-8")
ci_text = ci_text.replace(
    "php -S 127.0.0.1:8080 -t www/ > /tmp/server.log 2>&1 &",
    "php -S 127.0.0.1:8080 -t www/ www/router.php > /tmp/server.log 2>&1 &"
)
ci_path.write_text(ci_text, encoding="utf-8")
print("✅ Patched: .github/workflows/ci.yml (using router for php -S)")

# 4. Проверка синтаксиса
php_bin = "E:/localserver/tools/php8.5.12/php.exe"
ini_path = "E:/localserver/tools/php8.5.12/php.ini"
for f in [test_path, router_path]:
    res = subprocess.run([php_bin, "-c", ini_path, "-l", str(f)], capture_output=True, text=True)
    print(f"Syntax OK: {f}" if res.returncode == 0 else f"Syntax Error in {f}:\n{res.stderr}")