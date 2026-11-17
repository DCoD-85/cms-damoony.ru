<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use JsonException;
use RuntimeException;

/**
 * Общая запись файлов блока/филлера (block:edit, filler:edit).
 *
 * Держит все guard'ы в одном месте: размеры, валидация rules.json,
 * запрет сырого PHP без allow_php_blocks + --allow_php.
 */
final class BlockFileEditor
{
    public const FILES = ['code', 'rules', 'editor'];
    public const TYPES = ['script', 'visual', 'mixed'];

    private const MAX_RULES_SIZE = 65536;
    private const MAX_EDITOR_SIZE = 1048576;
    private const MAX_CODE_SIZE = 1048576;

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * Проверить и записать файл блока.
     *
     * @throws RuntimeException
     */
    public function write(
        string $blockDir,
        string $name,
        string $file,
        string $content,
        bool $allowPhpFlag,
    ): string {
        if (!in_array($file, self::FILES, true)) {
            throw new RuntimeException('Недопустимый file (разрешены: code, rules, editor)');
        }

        $maxSize = match ($file) {
            'rules' => self::MAX_RULES_SIZE,
            'editor' => self::MAX_EDITOR_SIZE,
            default => self::MAX_CODE_SIZE,
        };
        if (strlen($content) > $maxSize) {
            throw new RuntimeException('Файл больше ' . $maxSize . ' байт');
        }

        if ($file === 'rules') {
            $this->assertRules($content);
        }
        if ($file === 'editor') {
            $this->assertEditor($content);
        }
        if ($file === 'code') {
            if (!$this->allowPhpBlocks()) {
                throw new RuntimeException(
                    'Raw PHP в блоках запрещён (security.allow_php_blocks=false в engine.config)',
                );
            }
            if (!$allowPhpFlag) {
                throw new RuntimeException('Используйте --allow_php для подтверждения записи сырого PHP');
            }
            if (!str_starts_with($content, '<?php')) {
                throw new RuntimeException('PHP-код должен начинаться с <?php');
            }
        }

        if (!Fs::isDir($blockDir)) {
            throw new RuntimeException('Блок не найден');
        }

        $target = $blockDir . '/' . $name . match ($file) {
            'code' => '.php',
            'rules' => '.rules.json',
            default => '.editor.json',
        };

        Fs::writeAtomic($target, $content);

        return $target;
    }

    private function assertRules(string $content): void
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Некорректный JSON: ' . $e->getMessage());
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('rules.json должен быть объектом');
        }
        if (isset($decoded['type']) && !in_array($decoded['type'], self::TYPES, true)) {
            throw new RuntimeException('Недопустимый type (разрешены: ' . implode(', ', self::TYPES) . ')');
        }
        if (isset($decoded['enabled']) && !is_bool($decoded['enabled'])) {
            throw new RuntimeException('enabled должен быть bool');
        }
    }

    private function assertEditor(string $content): void
    {
        if (trim($content) === '') {
            throw new RuntimeException('editor.json пуст');
        }

        try {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Некорректный JSON: ' . $e->getMessage());
        }
    }

    private function allowPhpBlocks(): bool
    {
        $file = $this->paths->root() . '/config/engine.config';
        if (!is_file($file)) {
            return false;
        }

        $config = Config::fromFile($file)->all();

        return ($config['security']['allow_php_blocks'] ?? false) === true;
    }
}
