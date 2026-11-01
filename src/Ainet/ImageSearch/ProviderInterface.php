<?php

declare(strict_types=1);

namespace Damoony\Ainet\ImageSearch;

/**
 * Провайдер поиска картинок.
 * Возвращает список ImageResult. Может бросить исключение (обрабатывается в AinetFacade).
 */
interface ProviderInterface
{
    /** Уникальное имя провайдера (openverse, unsplash, pexels, wikimedia). */
    public function name(): string;

    /** Нужен ли API-ключ (для UI/диагностики). */
    public function requiresKey(): bool;

    /**
     * Поиск картинок по запросу.
     *
     * @param string $query запрос (1-100 символов, валидация выше)
     * @param int $count желаемое количество (1-20)
     *
     * @throws ProviderException
     *
     * @return list<ImageResult>
     *
     */
    public function search(string $query, int $count): array;
}
