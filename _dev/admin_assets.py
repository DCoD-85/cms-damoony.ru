from pathlib import Path
import subprocess

# 1. Создаем структуру папок для медиа админки
media_dir = Path("www/media/.admin/superadmin/skin/default/css")
media_dir.mkdir(parents=True, exist_ok=True)

# 2. Пишем базовый CSS для новых компонентов
css_content = """
/* =========================================
   Damoony Admin - Superadmin Skin
   ========================================= */

/* Layout */
.admin-wrapper { display: flex; height: 100vh; background: #f4f6f8; font-family: sans-serif; margin: 0; }
.admin-sidebar { width: 260px; background: #1e1e2d; color: #fff; display: flex; flex-direction: column; }
.admin-logo { padding: 24px 20px; font-size: 22px; font-weight: bold; border-bottom: 1px solid #2d2d3f; color: #fff; letter-spacing: 1px;}
.admin-menu { list-style: none; padding: 0; margin: 20px 0 0 0; }
.admin-menu li a { display: block; padding: 14px 24px; color: #a2a3b7; text-decoration: none; border-left: 4px solid transparent; transition: all 0.2s; font-size: 15px;}
.admin-menu li a:hover, .admin-menu li a.active { background: #1b1b28; color: #fff; border-left-color: #3699ff; }
.admin-main { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
.admin-header { background: #fff; padding: 20px 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.admin-header h1 { margin: 0; font-size: 26px; color: #111; font-weight: 600;}
.admin-content { padding: 30px; }

/* Компонент Card (Element) */
.ui-card { background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; border: 1px solid #eef0f4;}
.ui-card-header { padding: 18px 24px; background: #f8f9fa; border-bottom: 1px solid #eef0f4; font-weight: 600; color: #333; font-size: 18px;}
.ui-card-body { padding: 24px; }

/* Компонент Table (Element) */
.ui-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eef0f4;}
.ui-table th, .ui-table td { padding: 16px 24px; text-align: left; border-bottom: 1px solid #eef0f4; }
.ui-table th { background: #f8f9fa; font-weight: 600; color: #333; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;}
.ui-table tr:hover td { background: #fdfdfd; }

/* Компонент Button (Element) */
.ui-btn { display: inline-block; padding: 12px 24px; background: #3699ff; color: #fff; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-size: 15px; font-weight: 600; transition: background 0.2s;}
.ui-btn:hover { background: #2672c4; }

/* Компонент Box (Element) */
.ui-box { padding: 24px; background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 24px; border: 1px solid #eef0f4;}
"""

# Записываем файл
css_path = media_dir / "main.css"
css_path.write_text(css_content, encoding="utf-8")
print(f"Created: {css_path}")

# 3. Обновляем AdminLayout (добавляем поддержку ролей)
layout_path = Path("src/Admin/Ui/AdminLayout.php")
layout_text = layout_path.read_text(encoding="utf-8")

# Делаем так, чтобы AdminLayout принимал роль и подключал нужный CSS
find_sig = "public static function render(string $title, Node $content): Node"
replace_sig = "public static function render(string $title, Node $content, string $role = 'superadmin'): Node"

find_content = "        $wrapper = Builder::div('admin-wrapper');"
replace_content = """        // Подключаем CSS, привязанный к роли
        $cssPath = '/media/.admin/' . $role . '/skin/default/css/main.css';
        // В будущем здесь можно добавить $this->context->meta->addCss(...)
        
        $wrapper = Builder::div('admin-wrapper');"""

if find_sig in layout_text and find_content in layout_text:
    layout_text = layout_text.replace(find_sig, replace_sig)
    layout_text = layout_text.replace(find_content, replace_content)
    layout_path.write_text(layout_text, encoding="utf-8")
    print("Patched: src/Admin/Ui/AdminLayout.php")

# 4. Обновляем Dashboard (передаем роль и подключаем CSS)
dash_path = Path("app/.admin/superadmin/algoritm/algoritm.php")
dash_text = dash_path.read_text(encoding="utf-8")

# Меняем путь к CSS
find_css = "$this->context->meta->addCss('/media/admin/ui.css', 'admin');"
replace_css = "$this->context->meta->addCss('/media/.admin/' . $role . '/skin/default/css/main.css', 'admin');"

# Передаем роль в Layout
find_return = "return \\Damoony\\Admin\\Ui\\AdminLayout::render('Панель управления', $content);"
replace_return = "return \\Damoony\\Admin\\Ui\\AdminLayout::render('Панель управления', $content, $role);"

if find_css in dash_text:
    dash_text = dash_text.replace(find_css, replace_css)

if find_return in dash_text:
    dash_text = dash_text.replace(find_return, replace_return)

dash_path.write_text(dash_text, encoding="utf-8")
print("Patched: app/.admin/superadmin/algoritm/algoritm.php")

# 5. Проверка синтаксиса
php_bin = "E:/localserver/tools/php8.5.12/php.exe"
ini_path = "E:/localserver/tools/php8.5.12/php.ini"
for f in [layout_path, dash_path]:
    res = subprocess.run([php_bin, "-c", ini_path, "-l", str(f)], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"Syntax OK: {f}")
    else:
        print(f"Syntax Error in {f}:\\n{res.stderr}")