<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Assembly\AssemblyManifest;
use Damoony\Core\Files\Paths;
use Throwable;

/**
 * lang:derive — каркас перевода: добавляет недостающие ключи из base_lang
 * со значением "TODO: <текст базового языка>". Перевод делает человек/ИИ,
 * после чего lang:check перестаёт ругаться.
 */
final class LangDeriveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:derive';
    }

    public function description(): string
    {
        return 'Создать каркас перевода из base_lang (--assembly=, --to=, --from=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $assembly = trim((string) ($args['assembly'] ?? ''));
        $to = trim((string) ($args['to'] ?? ''));

        if ($assembly === '' || $to === '') {
            $r->error('Укажите --assembly= и --to= (например --to=en)');

            return $r;
        }

        try {
            $manifest = AssemblyManifest::fromDirectory($this->paths, $assembly);
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $from = trim((string) ($args['from'] ?? '')) ?: $manifest->baseLang();

        if (!in_array($from, $manifest->projectLangs(), true)) {
            $r->error('Базовый язык не входит в языки сценария: ' . $from);

            return $r;
        }
        if (!in_array($to, $manifest->projectLangs(), true)) {
            $r->error('Язык не входит в языки сценария: ' . $to);

            return $r;
        }
        if ($from === $to) {
            $r->error('Языки совпадают');

            return $r;
        }

        $dir = $this->paths->root() . '/stubs/assemblies/' . $assembly . '/app/.i18nglobal';
        $fromFile = $dir . '/' . $from . '.json';
        if (!is_file($fromFile)) {
            $r->error('Нет базового файла: ' . $from . '.json');

            return $r;
        }

        $base = json_decode((string) file_get_contents($fromFile), true);
        if (!is_array($base)) {
            $r->error('Некорректный JSON: ' . $from . '.json');

            return $r;
        }

        $toFile = $dir . '/' . $to . '.json';
        $target = is_file($toFile) ? json_decode((string) file_get_contents($toFile), true) : [];
        if (!is_array($target)) {
            $r->error('Некорректный JSON: ' . $to . '.json');

            return $r;
        }

        $added = 0;
        foreach ($base as $key => $value) {
            if (array_key_exists($key, $target) || !is_string($value)) {
                continue;
            }
            $target[$key] = 'TODO: ' . $value;
            $added++;
        }

        ksort($target);
        file_put_contents(
            $toFile,
            json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $r->data = ['assembly' => $assembly, 'from' => $from, 'to' => $to, 'added' => $added];
        $context->step('Добавлено ключей: ' . $added . ' (' . $to . ')', true, 'lang');

        return $r;
    }
}
