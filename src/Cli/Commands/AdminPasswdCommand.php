<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * admin:passwd — логин и пароль админки в config/admin.config.
 */
final class AdminPasswdCommand implements CommandInterface
{
    private const LOGIN_RE = '/^[a-zA-Z0-9_.\-]{3,32}$/';
    private const MIN_PASSWORD = 8;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'admin:passwd';
    }

    public function description(): string
    {
        return 'Установить логин и пароль админки';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $login = trim((string) ($args['name'] ?? 'admin'));
        $password = (string) ($args['password'] ?? '');

        if (preg_match(self::LOGIN_RE, $login) !== 1) {
            $r->error('Некорректный логин (3-32 символа: a-z, A-Z, 0-9, _, ., -)');

            return $r;
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            $r->error('Пароль минимум ' . self::MIN_PASSWORD . ' символов');

            return $r;
        }

        $file = $this->paths->root() . '/config/admin.config';
        $config = is_file($file) ? Config::fromFile($file)->all() : [];

        $config['login'] = $login;
        $config['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $config['default_lang'] ??= 'ru';
        $config['default_role'] ??= 'superadmin';

        if ($context->isDryRun()) {
            $context->step('Dry-run: пароль админки будет обновлён', true, $login);
            $r->data = ['login' => $login, 'dry_run' => true];

            return $r;
        }

        Fs::writeAtomic($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($file, $this->paths->root()));

        $r->data = ['login' => $login, 'file' => Fs::rel($file, $this->paths->root())];

        $context->step('Пароль обновлён', true, $login);

        return $r;
    }
}
