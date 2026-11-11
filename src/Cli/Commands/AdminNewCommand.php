<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class AdminNewCommand implements CommandInterface
{
    private const ROLE_RE = '/^[a-z][a-z0-9_]*$/';

    private const BLOCKS = ['top', 'left', 'center', 'right', 'bottom', 'policy', 'toast'];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'admin:new';
    }

    public function description(): string
    {
        return 'Создать админку роли (схема, 7 блоков, root, lang, login, logout, skin)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $role = (string) ($args['role'] ?? '');
        $force = isset($args['force']);

        if (!preg_match(self::ROLE_RE, $role)) {
            $r->error('Некорректная роль (a-z, 0-9, _)');
            return $r;
        }

        $adminRoot = $this->paths->root() . '/app/.admin';
        $roleDir = $adminRoot . '/' . $role;
        $mediaDir = $this->paths->root() . '/www/media/.admin/' . $role;

        if ((Fs::isDir($roleDir) || Fs::isDir($mediaDir)) && !$force) {
            $r->error(sprintf('Админка роли %s уже существует (используйте --force для перезаписи)', $role));
            return $r;
        }

        $adminConfigFile = $this->paths->root() . '/config/admin.config';
        if (!is_file($adminConfigFile)) {
            $r->error('admin.config не найден');
            return $r;
        }
        $adminConfig = Config::fromFile($adminConfigFile)->all();

        $defaultLang = (string) ($adminConfig['default_lang'] ?? '');
        if ($defaultLang === '') {
            $r->error('admin.config.default_lang не задан (сначала добавьте default_lang)');
            return $r;
        }

        // ===== 1. Схема =====
        $schemeDir = $adminRoot . '/.schemesglobal';
        Fs::mkdir($schemeDir);
        $schemePhp = $this->schemePhp($role);
        Fs::writeAtomic($schemeDir . '/admin.php', $schemePhp);
        Fs::writeAtomic($schemeDir . '/admin.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($schemeDir . '/admin.editor.json', json_encode([
            'fields' => [],
            'scheme' => ['children' => [], 'scripts' => [], 'meta' => ['css' => [], 'js' => []]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($schemeDir . '/admin.php', $this->paths->root()));

        // ===== 2. Блоки =====
        $blocksDir = $adminRoot . '/.blocksglobal';
        Fs::mkdir($blocksDir);
        foreach (self::BLOCKS as $blockName) {
            $dir = $blocksDir . '/' . $blockName;
            Fs::mkdir($dir);
            Fs::writeAtomic($dir . '/' . $blockName . '.php', $this->blockPhp($role, $blockName));
            Fs::writeAtomic($dir . '/' . $blockName . '.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
            Fs::writeAtomic($dir . '/' . $blockName . '.editor.json', json_encode([], JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($dir . '/' . $blockName . '.php', $this->paths->root()));
        }

        // ===== 3. Страница root =====
        Fs::mkdir($roleDir);
        $algoritmDir = $roleDir . '/algoritm';
        Fs::mkdir($algoritmDir);
        Fs::writeAtomic($algoritmDir . '/algoritm.php', $this->rootPagePhp($role));
        Fs::writeAtomic($algoritmDir . '/algoritm.rules.json', json_encode([
            'auth' => true,
            'available' => ['lang' => '^([a-z]{2})$', '' => '^$'],
        ], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($algoritmDir . '/algoritm.editor.json', "[]\n");
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        // ===== 4. Страница lang =====
        $langDir = $algoritmDir . '/lang';
        Fs::mkdir($langDir);
        Fs::writeAtomic($langDir . '/algoritm_lang.php', $this->langPagePhp($role));
        Fs::writeAtomic($langDir . '/algoritm_lang.rules.json', json_encode([
            'auth' => true,
            'available' => ['login' => '^login$', 'logout' => '^logout$', 'domains' => '^domains$', '' => '^$'],
        ], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($langDir . '/algoritm_lang.editor.json', "[]\n");
        $r->file(Fs::rel($langDir . '/algoritm_lang.php', $this->paths->root()));

        // ===== 5. login =====
        $loginDir = $langDir . '/login';
        Fs::mkdir($loginDir);
        Fs::writeAtomic($loginDir . '/algoritm_lang_login.php', $this->loginPagePhp($role));
        Fs::writeAtomic($loginDir . '/algoritm_lang_login.rules.json', json_encode(['auth' => false, 'available' => ['' => '^$']], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($loginDir . '/algoritm_lang_login.editor.json', "[]\n");
        $r->file(Fs::rel($loginDir . '/algoritm_lang_login.php', $this->paths->root()));

        // ===== 6. logout =====
        $logoutDir = $langDir . '/logout';
        Fs::mkdir($logoutDir);
        Fs::writeAtomic($logoutDir . '/algoritm_lang_logout.php', $this->logoutPagePhp($role));
        Fs::writeAtomic($logoutDir . '/algoritm_lang_logout.rules.json', json_encode(['auth' => false, 'available' => ['' => '^$']], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($logoutDir . '/algoritm_lang_logout.editor.json', "[]\n");
        $r->file(Fs::rel($logoutDir . '/algoritm_lang_logout.php', $this->paths->root()));

        // ===== 6a. domains (редирект) =====
        $domainsDir = $langDir . '/domains';
        Fs::mkdir($domainsDir);
        Fs::writeAtomic($domainsDir . '/algoritm_lang_domains.php', $this->domainsRedirectPhp($role));
        Fs::writeAtomic($domainsDir . '/algoritm_lang_domains.rules.json', json_encode([
            'auth' => true,
            'available' => ['langs' => '^langs$', '' => '^$'],
        ], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($domainsDir . '/algoritm_lang_domains.editor.json', "[]\n");
        $r->file(Fs::rel($domainsDir . '/algoritm_lang_domains.php', $this->paths->root()));

        // ===== 6b. domains/langs =====
        $langsDir = $domainsDir . '/langs';
        Fs::mkdir($langsDir);
        Fs::writeAtomic($langsDir . '/algoritm_lang_domains_langs.php', $this->domainsLangsPhp($role));
        Fs::writeAtomic($langsDir . '/algoritm_lang_domains_langs.rules.json', json_encode([
            'auth' => true,
            'available' => [
                'domain' => '^([a-z0-9][a-z0-9.-]*\\.[a-z]{2,})$',
                'allias' => '^([a-z][a-z0-9_]*)$',
                '' => '^$',
            ],
        ], JSON_PRETTY_PRINT) . "\n");
        Fs::writeAtomic($langsDir . '/algoritm_lang_domains_langs.editor.json', "[]\n");
        $r->file(Fs::rel($langsDir . '/algoritm_lang_domains_langs.php', $this->paths->root()));

        // ===== 7. Skin =====
        $skinDir = $mediaDir . '/skin/default';
        Fs::mkdir($skinDir . '/css');
        Fs::mkdir($skinDir . '/js');
        Fs::mkdir($skinDir . '/img');
        Fs::writeAtomic($skinDir . '/css/main.css', $this->mainCss());
        Fs::writeAtomic($skinDir . '/css/components.css', $this->componentsCss());
        Fs::writeAtomic($skinDir . '/js/main.js', '// admin skin: ' . $role . "\n");
        Fs::writeAtomic($skinDir . '/img/.gitkeep', '');
        Fs::writeAtomic($skinDir . '/skin.json', json_encode([
            'name' => 'default',
            'version' => '1.0',
        ], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($skinDir . '/css/main.css', $this->paths->root()));

        // ===== 8. admin.config: default_role, если пусто =====
        if (($adminConfig['default_role'] ?? '') === '') {
            $adminConfig['default_role'] = $role;
            Fs::writeAtomic(
                $adminConfigFile,
                json_encode($adminConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
            $context->step('default_role установлен', true, $role);
        }

        // Domain-level заготовки (пустые)
        Fs::mkdir($adminRoot . '/.dbglobal');
        Fs::writeAtomic($adminRoot . '/.dbglobal/.gitkeep', '');

        // Role-level заготовки (пустые)
        Fs::mkdir($roleDir . '/.blockslocal');
        Fs::writeAtomic($roleDir . '/.blockslocal/.gitkeep', '');
        Fs::mkdir($roleDir . '/.schemeslocal');
        Fs::writeAtomic($roleDir . '/.schemeslocal/.gitkeep', '');
        Fs::mkdir($roleDir . '/.dblocal');
        Fs::writeAtomic($roleDir . '/.dblocal/.gitkeep', '');

        $context->step('Админка создана', true, $role);

        return $r;
    }

    private function schemePhp(string $role): string
    {
        $css = '/media/.admin/' . $role . '/skin/{skin}/css/main.css';
        $css2 = '/media/.admin/' . $role . '/skin/{skin}/css/components.css';
        $js = '/media/.admin/' . $role . '/skin/{skin}/js/main.js';

        return <<<PHP
            <?php

            declare(strict_types=1);

            return [
                'tag' => 'div',
                'class' => 'admin-wrapper',
                'children' => [
                    ['tag' => 'div', 'class' => 'admin-top', 'slot' => 'top'],
                    [
                        'tag' => 'div',
                        'class' => 'admin-middle',
                        'children' => [
                            ['tag' => 'div', 'class' => 'admin-left', 'slot' => 'left'],
                            ['tag' => 'div', 'class' => 'admin-center', 'slot' => 'center'],
                            ['tag' => 'div', 'class' => 'admin-right', 'slot' => 'right'],
                        ],
                    ],
                    ['tag' => 'div', 'class' => 'admin-bottom', 'slot' => 'bottom'],
                    ['tag' => 'div', 'class' => 'admin-policy', 'slot' => 'policy'],
                    ['tag' => 'div', 'class' => 'admin-toast', 'slot' => 'toast'],
                ],
                'scripts' => [],
                'meta' => [
                    'css' => ['{$css}', '{$css2}'],
                    'js' => ['{$js}', '/media/shared/scripts/cliajax.js'],
                ],
            ];

            PHP;
    }

    private function blockPhp(string $role, string $name): string
    {
        $class = 'blocksglobal_' . $name;

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Region\\RegionContext;
            use Damoony\\UI\\Region\\RegionInterface;
            use Damoony\\UI\\Builder;
            use Damoony\\UI\\Node;

            final class {$class} implements RegionInterface
            {
                public function name(): string
                {
                    return '{$name}';
                }

                public function enabled(): bool
                {
                    return true;
                }

                public function render(RegionContext \$context): Node
                {
                    return Builder::div('admin-{$name}-inner');
                }
            }

            PHP;
    }

    private function rootPagePhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\AdminPage;
            use Damoony\\Core\\Config;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Node;

            final class algoritm extends AdminPage
            {
                protected function content(): Node|Response|null
                {
                    \$adminConfig = \$this->context->service('admin_config');
                    \$defaultLang = \$adminConfig instanceof Config
                        ? (string) \$adminConfig->getByKey(['default_lang'], '')
                        : '';
                    if (\$defaultLang === '') {
                        return Response::internalError('admin.config.default_lang not set');
                    }

                    return Response::redirect('/admin/' . \$defaultLang . '/');
                }
            }

            PHP;
    }

    private function langPagePhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\AdminPage;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Builder;
            use Damoony\\UI\\Node;

            final class algoritm_lang extends AdminPage
            {
                protected function content(): Node|Response|null
                {
                    \$t = \$this->context->service('i18n');
                    \$text = \$t instanceof \\Damoony\\Core\\I18n\\Translator ? \$t->t('admin.start.placeholder') : 'admin.start.placeholder';

                    return Builder::div('admin-start')->content(\$text);
                }
            }

            PHP;
    }

    private function loginPagePhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\Page;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Node;
            use Damoony\\UI\\Builder;
            use Damoony\\UI\\Components\\Card;
            use Damoony\\UI\\Components\\Form;
            use Damoony\\UI\\Components\\Input;
            use Damoony\\UI\\Components\\Button;
            use Damoony\\UI\\Components\\Label;

            final class algoritm_lang_login extends Page
            {
                protected function content(): Node|Response|null
                {
                    \$auth = \$this->context->service('auth');
                    \$csrf = \$this->context->service('csrf');
                    \$lang = (string) (\$this->args['lang'] ?? '');

                    \$error = '';

                    if (\$this->context->request->isPost()) {
                        \$login = (string) \$this->context->request->post('login');
                        \$password = (string) \$this->context->request->post('password');

                        if (\$auth instanceof \\Damoony\\Admin\\AuthInterface && \$auth->attempt(\$login, \$password)) {
                            return Response::redirect('/admin/' . \$lang . '/');
                        }
                        \$error = 'Неверный логин или пароль';
                    }

                    \$form = new Form();
                    \$form->action('/admin/' . \$lang . '/login')->method('POST');

                    if (\$csrf instanceof \\Damoony\\Core\\Security\\Csrf) {
                        \$token = new Input('hidden', '_csrf');
                        \$token->attrs()->add('value', \$csrf->token());
                        \$form->add(\$token);
                    }

                    \$form->add(new Label('Логин'));
                    \$form->add(new Input('text', 'login'));

                    \$form->add(new Label('Пароль'));
                    \$form->add(new Input('password', 'password'));

                    \$form->add(new Button('Войти'));

                    \$card = new Card('Вход в админку');
                    if (\$error !== '') {
                        \$card->add(Builder::div('error-msg')->content(\$error));
                    }
                    \$card->add(\$form);

                    \$wrapper = Builder::div('login-page-wrapper');
                    \$wrapper->append(\$card);

                    return \$wrapper;
                }
            }

            PHP;
    }

    private function logoutPagePhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\Page;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Node;

            final class algoritm_lang_logout extends Page
            {
                protected function content(): Node|Response|null
                {
                    \$auth = \$this->context->service('auth');
                    if (\$auth instanceof \\Damoony\\Admin\\AuthInterface) {
                        \$auth->logout();
                    }
                    \$lang = (string) (\$this->args['lang'] ?? '');

                    return Response::redirect('/admin/' . \$lang . '/login');
                }
            }

            PHP;
    }

    private function domainsRedirectPhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\AdminPage;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Node;

            final class algoritm_lang_domains extends AdminPage
            {
                protected function content(): Node|Response|null
                {
                    \$lang = (string) (\$this->args['lang'] ?? 'ru');
                    return Response::redirect('/admin/' . \$lang . '/domains/langs');
                }
            }

            PHP;
    }

    private function domainsLangsPhp(string $role): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Damoony\\Admin\\Pages\\{$role};

            use Damoony\\UI\\Algoritm\\AdminPage;
            use Damoony\\Core\\Files\\Paths;
            use Damoony\\Core\\Http\\Response;
            use Damoony\\UI\\Builder;
            use Damoony\\UI\\Components\\Button;
            use Damoony\\UI\\Components\\Card;
            use Damoony\\UI\\Components\\Form;
            use Damoony\\UI\\Components\\Input;
            use Damoony\\UI\\Components\\Label;
            use Damoony\\UI\\Components\\Link;
            use Damoony\\UI\\Components\\Table;
            use Damoony\\UI\\Components\\Tbody;
            use Damoony\\UI\\Components\\Td;
            use Damoony\\UI\\Components\\Th;
            use Damoony\\UI\\Components\\Thead;
            use Damoony\\UI\\Components\\Tr;
            use Damoony\\UI\\Node;

            final class algoritm_lang_domains_langs extends AdminPage
            {
                protected function content(): Node|Response|null
                {
                    \$lang = (string) (\$this->args['lang'] ?? 'ru');
                    \$domain = (string) (\$this->args['domain'] ?? '');
                    \$alias = (string) (\$this->args['allias'] ?? '');

                    \$paths = \$this->context->service('paths');
                    if (!\$paths instanceof Paths) {
                        return Response::internalError('Paths missing');
                    }

                    \$phpBin = PHP_BINARY;
                    if (str_ends_with(\$phpBin, 'php-cgi.exe')) {
                        \$phpBin = str_replace('php-cgi.exe', 'php.exe', \$phpBin);
                    }
                    \$cliPath = \$paths->root() . '/bin/cli.php';

                    /** @param array<string, string> \$args */
                    \$runCli = function (string \$command, array \$args) use (\$phpBin, \$cliPath): array {
                        \$parts = [escapeshellarg(\$phpBin), escapeshellarg(\$cliPath), \$command];
                        foreach (\$args as \$k => \$v) {
                            \$parts[] = '--' . \$k . '=' . escapeshellarg(\$v);
                        }
                        \$cmd = implode(' ', \$parts) . ' 2>&1';
                        \$out = [];
                        \$ret = 0;
                        exec(\$cmd, \$out, \$ret);
                        \$json = json_decode(implode("\n", \$out), true);
                        return is_array(\$json) ? \$json : ['ok' => false, 'errors' => ['invalid CLI output'], 'data' => []];
                    };

                    \$baseUrl = '/admin/' . \$lang . '/domains/langs';
                    if (\$domain !== '') {
                        \$baseUrl .= '/' . rawurlencode(\$domain);
                        if (\$alias !== '') {
                            \$baseUrl .= '/' . rawurlencode(\$alias);
                        }
                    }

                    if (\$this->context->request->isPost()) {
                        \$action = (string) \$this->context->request->post('action', '');
                        \$langCode = (string) \$this->context->request->post('lang_code', '');

                        \$cliArgs = ['domain' => \$domain];
                        if (\$alias !== '') {
                            \$cliArgs['allias'] = \$alias;
                        }

                        \$cmd = '';
                        if (\$action === 'add') {
                            \$cmd = 'lang:add';
                            \$cliArgs['lang'] = \$langCode;
                        } elseif (\$action === 'remove') {
                            \$cmd = 'lang:remove';
                            \$cliArgs['lang'] = \$langCode;
                            \$cliArgs['force'] = '1';
                        } elseif (\$action === 'repair') {
                            \$cmd = 'lang:repair';
                            \$cliArgs['fix'] = '1';
                        }

                        if (\$cmd !== '') {
                            \$res = \$runCli(\$cmd, \$cliArgs);
                            \$ok = (\$res['ok'] ?? false) === true;
                            \$errors = (array) (\$res['errors'] ?? []);
                            \$_SESSION['flash'][] = [
                                'type' => \$ok ? 'success' : 'error',
                                'message' => \$ok ? 'OK: ' . \$cmd : 'Ошибка: ' . implode('; ', array_map('strval', \$errors)),
                            ];
                            return Response::redirect(\$baseUrl);
                        }
                    }

                    \$content = Builder::div('');
                    \$content->append(Builder::h(2)->content('Языки'));

                    if (\$domain === '') {
                        \$res = \$runCli('domain:list', ['json' => '1']);
                        /** @var list<array<string, mixed>> \$domainRows */
                        \$domainRows = (array) (\$res['data']['rows'] ?? []);
                        \$ul = Builder::ul('domains-list');
                        foreach (\$domainRows as \$row) {
                            \$d = (string) (\$row['domain'] ?? '');
                            if (\$d === '') {
                                continue;
                            }
                            \$li = Builder::li();
                            \$li->append(new Link('/admin/' . \$lang . '/domains/langs/' . rawurlencode(\$d), \$d));
                            \$aliases = (array) (\$row['aliases'] ?? []);
                            if (\$aliases !== []) {
                                \$li->append(Builder::span()->content(' — ' . implode(', ', array_map('strval', \$aliases))));
                            }
                            \$ul->append(\$li);
                        }
                        \$content->append(\$ul);
                        \$this->setContent('center', \$content);
                        return \$content;
                    }

                    \$content->append(Builder::h(3)->content('Домен: ' . \$domain . (\$alias !== '' ? ' / ' . \$alias : '')));

                    if (\$alias === '') {
                        \$domRes = \$runCli('domain:list', ['json' => '1']);
                        \$domRows = (array) (\$domRes['data']['rows'] ?? []);
                        \$aliases = [];
                        foreach (\$domRows as \$dr) {
                            if ((string) (\$dr['domain'] ?? '') === \$domain) {
                                \$aliases = (array) (\$dr['aliases'] ?? []);
                                break;
                            }
                        }
                        if (\$aliases !== []) {
                            \$content->append(Builder::h(4)->content('Алиасы'));
                            \$al = Builder::ul('aliases-list');
                            foreach (\$aliases as \$a) {
                                \$a = (string) \$a;
                                if (\$a === '') {
                                    continue;
                                }
                                \$al->append(Builder::li()->append(
                                    new Link('/admin/' . \$lang . '/domains/langs/' . rawurlencode(\$domain) . '/' . rawurlencode(\$a), \$a)
                                ));
                            }
                            \$content->append(\$al);
                        }
                    }

                    \$listArgs = ['domain' => \$domain, 'with-config' => '1', 'json' => '1'];
                    if (\$alias !== '') {
                        \$listArgs['allias'] = \$alias;
                    }
                    \$res = \$runCli('lang:list', \$listArgs);
                    \$data = (array) (\$res['data'] ?? []);
                    /** @var list<array<string, mixed>> \$rows */
                    \$rows = (array) (\$data['rows'] ?? []);

                    \$table = new Table();
                    \$thead = new Thead();
                    \$trHead = new Tr();
                    \$trHead->add(new Th('Язык'));
                    \$trHead->add(new Th('Файл'));
                    \$trHead->add(new Th('В config'));
                    \$trHead->add(new Th('Действия'));
                    \$thead->add(\$trHead);
                    \$table->add(\$thead);

                    \$tbody = new Tbody();
                    if (\$rows === []) {
                        \$tr = new Tr();
                        \$td = new Td();
                        \$td->attrs()->add('colspan', '4');
                        \$td->content('Языков нет');
                        \$tr->add(\$td);
                        \$tbody->add(\$tr);
                    } else {
                        foreach (\$rows as \$row) {
                            \$rowLang = (string) (\$row['lang'] ?? '');
                            \$tr = new Tr();
                            \$tr->add((new Td())->content(\$rowLang));
                            \$tr->add((new Td())->content(is_string(\$row['file'] ?? null) ? \$row['file'] : '—'));
                            \$tr->add((new Td())->content((\$row['in_config'] ?? false) === true ? 'yes' : 'no'));

                            \$removeForm = new Form();
                            \$removeForm->action(\$baseUrl)->method('POST');
                            \$removeForm->add(new Input('hidden', 'action', 'remove'));
                            \$removeForm->add(new Input('hidden', 'lang_code', \$rowLang));
                            \$removeForm->add(new Button('Удалить'));
                            \$tr->add((new Td())->add(\$removeForm));
                            \$tbody->add(\$tr);
                        }
                    }
                    \$table->add(\$tbody);
                    \$content->append(\$table);

                    \$addCard = new Card('Добавить язык');
                    \$addForm = new Form();
                    \$addForm->action(\$baseUrl)->method('POST');
                    \$addForm->add(new Input('hidden', 'action', 'add'));
                    \$addForm->add(new Label('Код языка (ISO 639-1)'));
                    \$addForm->add(new Input('text', 'lang_code', ''));
                    \$addForm->add(new Button('Добавить'));
                    \$addCard->add(\$addForm);
                    \$content->append(\$addCard);

                    \$this->setContent('center', \$content);
                    return \$content;
                }
            }

            PHP;
    }

    private function mainCss(): string
    {
        return <<<CSS
            /* Admin skin: main */
            body { font-family: system-ui, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
            .admin-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
            .admin-top { background: #1f2937; color: #fff; padding: 8px 16px; }
            .admin-middle { display: flex; flex: 1; }
            .admin-left { width: 220px; background: #fff; border-right: 1px solid #e5e7eb; }
            .admin-center { flex: 1; padding: 16px; }
            .admin-right { width: 220px; background: #fff; border-left: 1px solid #e5e7eb; }
            .admin-bottom { background: #1f2937; color: #fff; padding: 8px 16px; }
            .admin-policy { background: #111827; color: #9ca3af; padding: 6px 16px; font-size: 12px; }
            .admin-toast { position: fixed; bottom: 16px; right: 16px; z-index: 1000; }
            .admin-start { font-size: 20px; color: #374151; }
            .login-page-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            CSS;
    }

    private function componentsCss(): string
    {
        return <<<CSS
            /* Admin skin: components */
            .error-msg { color: #b91c1c; padding: 8px; border: 1px solid #fca5a5; background: #fee2e2; border-radius: 4px; margin-bottom: 8px; }
            .ui-btn { display: inline-block; padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; color: #111827; text-decoration: none; cursor: pointer; }
            .ui-btn:hover { background: #f3f4f6; }
            CSS;
    }
}
