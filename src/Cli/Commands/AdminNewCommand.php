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
            'available' => ['login' => '^login$', 'logout' => '^logout$', '' => '^$'],
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
        $css = '/media/.admin/' . $role . '/skin/default/css/main.css';
        $css2 = '/media/.admin/' . $role . '/skin/default/css/components.css';
        $js = '/media/.admin/' . $role . '/skin/default/js/main.js';

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
        $class = 'blockglobal_' . $name;

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
                    \$role = \$this->context->service('admin_role');
                    if (!is_string(\$role) || \$role === '') {
                        \$adminConfig = \$this->context->service('admin_config');
                        \$role = \$adminConfig instanceof \\Damoony\\Core\\Config
                            ? (string) \$adminConfig->getByKey(['default_role'], '')
                            : '';
                    }
                    if (\$role !== '') {
                        \$this->context->meta->addCss('/media/.admin/' . \$role . '/skin/default/css/main.css');
                    }

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
