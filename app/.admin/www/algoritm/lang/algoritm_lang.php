<?php

declare(strict_types=1);

namespace admin_server\www;

use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\Admin\AdminModule;
use Damoony\Modules\AuthAdmin\AdminAccess;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

final class algoritm_lang extends Page
{
    private const CSRF_COOKIE = 'ADMIN_CSRF';

    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $access = $this->context->service('admin_access');
        $auth = $this->context->service('admin_auth');
        if (!$access instanceof AdminAccess || !$auth instanceof AdminAuthService) {
            return Response::notFound();
        }
        if (!$access->allows($this->context->request)) {
            return Response::notFound();
        }

        $csrf = (string) ($this->context->request->cookies[self::CSRF_COOKIE] ?? '');
        if ($csrf === '') {
            $csrf = bin2hex(random_bytes(32));
            $this->cookie(self::CSRF_COOKIE, $csrf, 3600);
        }

        $error = null;
        if ($this->isPost()) {
            $posted = (string) $this->context->request->post('_csrf', '');
            $ip = (string) ($this->context->request->server['REMOTE_ADDR'] ?? '');

            if (!$this->rateLimitAllows($ip)) {
                $error = 'Слишком много попыток, попробуйте позже';
            } elseif (!hash_equals($csrf, $posted)) {
                $error = 'Сессия устарела, обновите страницу';
            } else {
                $result = $auth->login(
                    (string) $this->context->request->post('login'),
                    (string) $this->context->request->post('password'),
                    $ip,
                    (string) ($this->context->request->header('user-agent') ?? ''),
                );

                if ($result === null) {
                    $this->rateLimitHit($ip);
                    $error = 'Неверный логин или пароль';
                } else {
                    $this->cookie(AdminModule::COOKIE, $result['token'], AdminAuthService::SESSION_TTL);

                    return Response::redirect('/' . $lang . '/');
                }
            }
        }

        $session = $auth->authenticate((string) ($this->context->request->cookies[AdminModule::COOKIE] ?? ''));

        $wrap = Builder::div('admin-box');
        if ($session === null) {
            $wrap->append(Builder::h(1)->content('Вход в админку'));
            if ($error !== null) {
                $wrap->append(Builder::p('admin-error')->content($error));
            }

            $form = new Form();
            $form->method('POST');
            $form->action('/' . $lang . '/');
            $form->add(new Input('hidden', '_csrf', $csrf));
            $form->add(new Input('text', 'login'));
            $form->add(new Input('password', 'password'));
            $form->add(new Button('Войти'));
            $wrap->append($form);

            return $wrap;
        }

        $role = (string) $session['role'];
        $wrap->append(Builder::h(1)->content('Админка сервера'));
        $wrap->append(Builder::p()->content('Роль: ' . $role));
        $wrap->append(Builder::p()->content('Аккаунт: ' . (string) $session['account']['login']));

        if ($role === AdminAuthService::ROLE_SUPERADMIN) {
            $site = $this->context->service('site_config');
            if ($site instanceof SiteConfig) {
                $list = Builder::ul('admin-projects');
                foreach ($site->projects() as $key => $project) {
                    $list->append(Builder::li()->content(
                        (string) $key . ' — mirrors: ' . implode(', ', $project->hostNames())
                        . ' · aliases: ' . implode(', ', $project->aliasNames())
                        . ' · langs: ' . implode(', ', $project->langs),
                    ));
                }
                $wrap->append($list);
            }
        } else {
            $wrap->append(Builder::p()->content('Домен: ' . (string) $session['scope']));
        }

        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/logout/')->content('Выйти'),
        ));

        return $wrap;
    }

    private function cookie(string $name, string $value, int $ttl): void
    {
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function rateLimitAllows(string $ip): bool
    {
        $file = $this->rateLimitFile($ip);
        if ($file === null) {
            return true;
        }
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || (int) ($data['reset'] ?? 0) < time()) {
            return true;
        }

        return (int) ($data['count'] ?? 0) < 10;
    }

    private function rateLimitHit(string $ip): void
    {
        $file = $this->rateLimitFile($ip);
        if ($file === null) {
            return;
        }
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || (int) ($data['reset'] ?? 0) < time()) {
            $data = ['count' => 0, 'reset' => time() + 600];
        }
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        file_put_contents($file, json_encode($data));
    }

    private function rateLimitFile(string $ip): ?string
    {
        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return null;
        }
        $dir = $paths->root() . '/var/admin-ratelimit';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        return $dir . '/' . sha1($ip) . '.json';
    }
}
