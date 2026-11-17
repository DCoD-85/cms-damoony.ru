<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Cli\Result;

/**
 * Адрес страницы из аргументов CLI: имя + родитель.
 *
 * __root__ — корневая страница алиаса (name = '', parent = '').
 */
final class PageAddress
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';
    private const PARENT_RE = '#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#';

    private function __construct(
        public readonly string $name,
        public readonly string $parent,
    ) {
    }

    public function isRoot(): bool
    {
        return $this->name === '';
    }

    public function display(): string
    {
        return $this->isRoot() ? '__root__' : $this->name;
    }

    /**
     * Разбирает аргументы; при ошибке записывает её в Result и возвращает null.
     *
     * @param array<string, string> $args
     */
    public static function fromArgs(array $args, Result $r): ?self
    {
        $name = (string) ($args['name'] ?? '');
        $parent = trim((string) ($args['parent'] ?? ''), '/');

        if ($name === '__root__') {
            if ($parent !== '') {
                $r->error('--parent несовместим с __root__');

                return null;
            }

            return new self('', '');
        }

        if ($name === '') {
            $r->error('Укажите --name (или __root__ для главной страницы)');

            return null;
        }
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя страницы (a-z, 0-9, _; начинается с буквы)');

            return null;
        }
        if ($parent !== '' && preg_match(self::PARENT_RE, $parent) !== 1) {
            $r->error('Некорректный путь родительской страницы');

            return null;
        }

        return new self($name, $parent);
    }
}
