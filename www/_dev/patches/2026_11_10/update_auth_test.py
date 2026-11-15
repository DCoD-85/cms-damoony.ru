path = '_dev/tests/Unit/Routing/FileRouterTest.php'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read().replace('\r\n', '\n')

old_test = """    public function testAuthNotInheritedFromRoot(): void
    {
        $this->writePage('example.ru', 'www', '', ['available' => ['about'], 'auth' => true]);
        $this->writePage('example.ru', 'www', 'about', ['available' => ['']]);
        $m = $this->router()->match('/about', $this->ctx());
        self::assertTrue($m->found);
        self::assertFalse($m->auth);
    }"""

new_test = """    public function testAuthInheritedFromRoot(): void
    {
        // Новый контракт: auth на предке накапливается в потомке
        $this->writePage('example.ru', 'www', '', ['available' => ['about'], 'auth' => true]);
        $this->writePage('example.ru', 'www', 'about', ['available' => ['']]);
        $m = $this->router()->match('/about', $this->ctx());
        self::assertTrue($m->found);
        self::assertTrue($m->auth);
    }

    public function testAuthInheritedMultiLevel(): void
    {
        // auth: true на глубоком предке — наследуется через все уровни
        $this->writePage('example.ru', 'www', '', ['available' => ['admin'], 'auth' => false]);
        $this->writePage('example.ru', 'www', 'admin', ['available' => ['users'], 'auth' => true]);
        $this->writePage('example.ru', 'www', 'admin/users', ['available' => ['']]);
        $m = $this->router()->match('/admin/users', $this->ctx());
        self::assertTrue($m->found);
        self::assertTrue($m->auth);
    }

    public function testAuthOpenBranch(): void
    {
        // Вся цепочка без auth → auth=false
        $this->writePage('example.ru', 'www', '', ['available' => ['public'], 'auth' => false]);
        $this->writePage('example.ru', 'www', 'public', ['available' => ['']]);
        $m = $this->router()->match('/public', $this->ctx());
        self::assertTrue($m->found);
        self::assertFalse($m->auth);
    }"""

assert old_test in content, "testAuthNotInheritedFromRoot not found"
content = content.replace(old_test, new_test, 1)

with open(path, 'w', encoding='utf-8', newline='\n') as f:
    f.write(content)
print("✓ Tests updated: testAuthInheritedFromRoot + 2 new tests")
