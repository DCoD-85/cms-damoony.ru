<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class AdminPasswdCommand implements CommandInterface
{
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

        $login = $args['name'] ?? 'admin';
        $password = $args['password'] ?? '';

        if ($login === '') {
            $r->error('Логин не может быть пустым');
            return $r;
        }
        if ($password === '') {
            $r->error('Пароль не может быть пустым (--password=...)');
            return $r;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            $r->error('Не удалось создать хеш');
            return $r;
        }

        $file = $this->paths->root() . '/config/admin.config';
        $cfg = is_file($file) ? Config::fromFile($file)->all() : [];
        $cfg['login'] = $login;
        $cfg['password_hash'] = $hash;

        Fs::writeAtomic($file, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($file, $this->paths->root()));
        $context->step('Пароль обновлён', true, $login);

        return $r;
    }
}