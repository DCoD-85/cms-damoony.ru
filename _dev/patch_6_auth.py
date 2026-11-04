import pathlib

# --- Fix 1: aipreview.php ---
p1 = pathlib.Path('www/admin/aipreview.php')
src1 = p1.read_text(encoding='utf-8')

old1 = """    $session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $session->start();
    $adminSessionId = session_id();
"""
new1 = """    $session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $session->start();
    $adminLogin = $session->get('admin_login');
    if (!is_string($adminLogin) || $adminLogin === '') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Forbidden: Admin authentication required.');
    }
"""
assert old1 in src1, "[Fix 1] Pattern not found in aipreview.php"
src1 = src1.replace(old1, new1, 1)

# Удаляем старую бесполезную проверку
old2 = """    // Не проверяем admin_session_id строго: он может меняться при релогах.
    // Безопасность обеспечивается 64-битным tab_id (невозможно угадать).
    if ($adminSessionId === '') {
        throw new \\RuntimeException('Not authenticated');
    }
"""
assert old2 in src1, "[Fix 1b] Pattern not found in aipreview.php"
src1 = src1.replace(old2, "", 1)
p1.write_text(src1, encoding='utf-8')
print("OK: aipreview.php auth fixed.")

# --- Fix 2: ai_edit.php ---
p2 = pathlib.Path('www/admin/ai_edit.php')
src2 = p2.read_text(encoding='utf-8')

old3 = """    $sess = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $sess->start();
    $adminSessId = (string) session_id();
"""
new3 = """    $sess = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $sess->start();
    $adminSessId = (string) $sess->get('admin_login');
    if ($adminSessId === '') {
        throw new \\RuntimeException('Forbidden: Admin authentication required.');
    }
"""
assert old3 in src2, "[Fix 2] Pattern not found in ai_edit.php"
src2 = src2.replace(old3, new3, 1)
p2.write_text(src2, encoding='utf-8')
print("OK: ai_edit.php auth fixed.")
