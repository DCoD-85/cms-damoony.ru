import pathlib

p = pathlib.Path('_dev/tests/Unit/Cli/SessionCommandsTest.php')
src = p.read_text(encoding='utf-8')

# --- Fix 1: testApplyRejectsOnHashMismatch — передаём admin-sess, чтобы дойти до хэша
old1 = """        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Conflict', $r->errors[0]);"""
new1 = """        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'dummy',
        ], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Conflict', $r->errors[0]);"""
assert old1 in src, "[Fix 1] Pattern not found"
assert src.count(old1) == 1, "[Fix 1] Pattern not unique"
src = src.replace(old1, new1, 1)

# --- Fix 2: testApplyRejectsWrongAdminSession — заменяем на два теста (empty + ignored)
old2 = """    public function testApplyRejectsWrongAdminSession(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4', 'admin-sess' => 'mysess'], $this->ctx());

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'other',
        ], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Access denied', $r->errors[0]);
    }"""
new2 = """    public function testApplyRejectsEmptyAdminSession(): void
    {
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4'], $this->ctx());

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
        ], $this->ctx());

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Access denied', $r->errors[0]);
    }

    public function testApplyIgnoresMismatchedAdminSession(): void
    {
        // Новый контракт: admin_session_id не сравнивается (может меняться при релогине)
        $start = new SessionStartCommand($this->paths);
        $start->run(['domain' => 'damoony3.ru', 'tab' => 'a1b2c3d4', 'admin-sess' => 'mysess'], $this->ctx());

        $apply = new SessionApplyCommand($this->paths);
        $r = $apply->run([
            'domain' => 'damoony3.ru',
            'tab' => 'a1b2c3d4',
            'admin-sess' => 'other',
        ], $this->ctx());

        $this->assertTrue($r->ok);
        $this->assertTrue($r->data['applied']);
    }"""
assert old2 in src, "[Fix 2] Pattern not found"
assert src.count(old2) == 1, "[Fix 2] Pattern not unique"
src = src.replace(old2, new2, 1)

p.write_text(src, encoding='utf-8')
print("OK: SessionCommandsTest patched (2 fixes, +1 new test).")
