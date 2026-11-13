from pathlib import Path

BASE = Path.cwd()

LOGIN_FILES = [
    BASE / "stubs/assemblies/developer/app/www/algoritm/lang/login/algoritm_lang_login.php",
    BASE / "app/damoony4.ru/www/algoritm/lang/login/algoritm_lang_login.php",
]

OLD = """        $error = '';
        if ($this->isPost()) {
            $login = (string) $this->context->request->post('login');
            $password = (string) $this->context->request->post('password');
            
            if ($auth instanceof \\Damoony\\Data\\Auth\\AuthInterface && $auth->login($login, $password)) {
                return Response::redirect('/' . $this->context->domain->language . '/profile/');
            }
            $error = $this->context->lang('auth.error');
        }"""

NEW = """        if ($this->isPost()) {
            $login = (string) $this->context->request->post('login');
            $password = (string) $this->context->request->post('password');
            
            if ($auth instanceof \\Damoony\\Data\\Auth\\AuthInterface && $auth->login($login, $password)) {
                return Response::redirect('/' . $this->context->domain->language . '/profile/');
            }
            // Ошибка через flash → в AJAX прилетит как toast
            $flash = $this->context->service('flash');
            if ($flash instanceof \\Damoony\\Core\\Flash) {
                $flash->add('error', $this->context->lang('auth.error'));
            }
            // При нативном POST (без JS) — рендерим форму заново, тост не нужен
            if (!$this->isAjax()) {
                return Response::redirect($this->context->request->path);
            }
        }"""

for p in LOGIN_FILES:
    if not p.exists(): print(f"SKIP: {p}"); continue
    src = p.read_text(encoding="utf-8")
    if "flash->add('error'" in src: print(f"SKIP (already): {p}"); continue
    if OLD not in src:
        print(f"FAIL: marker not found in {p}")
        continue
    p.write_text(src.replace(OLD, NEW, 1), encoding="utf-8", newline="")
    print(f"OK: {p}")