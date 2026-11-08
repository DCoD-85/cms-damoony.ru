<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

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

        $login = trim((string) ($args['name'] ?? 'admin'));
        $password = (string) ($args['password'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $login)) {
            $r->error('Некорректный логин (3-32 символа: a-z, A-Z, 0-9, _, ., -)');
            return $r;
        }
        if (strlen($password) < 8) {
            $r->error('Пароль минимум 8 символов');
            return $r;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

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
