<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Identity\IdentityStore;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use RuntimeException;

/**
 * cli:init — создать идентификатор владельца CLI (bootstrap, доступен без идентификатора).
 */
final class InitCommand implements CommandInterface
{
    public function __construct(
        private readonly IdentityStore $store,
        private readonly string $root,
    ) {
    }

    public function name(): string
    {
        return 'cli:init';
    }

    public function description(): string
    {
        return 'Создать идентификатор владельца CLI';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $user = trim((string) ($args['name'] ?? ''));
        if ($user === '') {
            $user = (string) (getenv('USERNAME') ?: getenv('USER') ?: 'owner');
        }

        if (preg_match('/^[a-zA-Z0-9_.@\-]{1,64}$/', $user) !== 1) {
            $r->error('Некорректное имя владельца CLI');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: идентификатор CLI будет создан для ' . $user);
            $r->data = ['user' => $user, 'file' => 'var/cli.identity', 'dry_run' => true];

            return $r;
        }

        try {
            $identity = $this->store->create($user, isset($args['force']));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $r->file(Fs::rel($this->store->path(), $this->root));

        $r->data = [
            'user' => $identity['user'],
            'file' => Fs::rel($this->store->path(), $this->root),
        ];

        $context->step('Идентификатор CLI создан', true, $identity['user']);

        return $r;
    }
}
