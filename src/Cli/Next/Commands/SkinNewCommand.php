<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * skin:new для схемы domains_app.
 */
final class SkinNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'skin:new';
    }

    public function description(): string
    {
        return 'Создать скин алиаса (css/main.css, js/main.js, img/, skin.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? 'default');
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя скина (a-z0-9_)');

            return $r;
        }

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $skinDir = $this->paths->media($target->folder(), $target->aliasName()) . '/skin/' . $name;
        if (Fs::isDir($skinDir)) {
            $r->error('Скин уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: скин %s для %s/%s будет создан', $name, $target->projectKey(), $target->aliasName()));
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'skin' => $name,
                'dry_run' => true,
            ];

            return $r;
        }

        Fs::mkdir($skinDir . '/css');
        Fs::mkdir($skinDir . '/js');
        Fs::mkdir($skinDir . '/img');

        $cssFile = $skinDir . '/css/main.css';
        $jsFile = $skinDir . '/js/main.js';

        Fs::writeAtomic($cssFile, "/* Skin: {$name} */\n");
        Fs::writeAtomic($jsFile, "// Skin: {$name}\n");
        Fs::writeAtomic(
            $skinDir . '/skin.json',
            json_encode(
                ['name' => $name, 'version' => '1.0', 'created_at' => gmdate('Y-m-d\TH:i:s\Z')],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ) . "\n",
        );

        foreach ([$cssFile, $jsFile, $skinDir . '/skin.json'] as $file) {
            $r->file(Fs::rel($file, $this->paths->root()));
        }

        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'skin' => $name];

        $context->step('Скин создан', true, $name);

        return $r;
    }
}
