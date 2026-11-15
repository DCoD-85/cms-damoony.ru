<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Абстракция транспорта — для тестируемости HttpClient без сети.
 * Реализация по умолчанию: CurlTransport.
 */
interface HttpTransportInterface
{
    /**
     * HTTP-запрос. Без follow redirects (это делает HttpClient).
     *
     * @param array{
     *     connect_timeout: int,
     *     read_timeout: int,
     *     max_size: int,
     *     method?: string,
     *     user_agent?: string
     * } $options
     *
     * @throws HttpTransportException
     *
     * @return array{status: int, headers: array<string, string>, body: string, truncated: bool}
     *
     */
    public function get(string $url, array $options): array;
}
