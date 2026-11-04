<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

/**
 * Контракт DataProvider: одна сущность (users, news, ...) в контексте (domain, alias).
 *
 * @see AbstractProvider — базовая реализация query() через PDO
 */
interface ProviderInterface
{
    /** Логическое имя ('user', 'news', 'files', ...) — для db:methods. */
    public function name(): string;

    /** Логическое имя таблицы без префикса ('users', 'news', ...). */
    public function table(): string;

    /** Физическое имя таблицы с префиксом ('d1_www__users') или без ('users'). */
    public function physicalTable(): string;

    public function domain(): string;

    public function alias(): string;
}
