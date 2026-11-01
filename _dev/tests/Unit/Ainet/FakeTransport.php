<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\HttpTransportException;
use Damoony\Ainet\HttpTransportInterface;

/**
 * Fake-транспорт для тестов HttpClient без реальной сети.
 * Сценарии: очередь ответов, ошибки, assertion на переданный URL.
 */
final class FakeTransport implements HttpTransportInterface
{
    /** @var list<array{status: int, headers: array<string, string>, body: string, truncated: bool}> */
    private array $responses = [];

    /** @var list<string> */
    private array $requestedUrls = [];

    /** @var array<string, HttpTransportException> */
    private array $errorsByUrl = [];

    /**
     * @param array{status: int, headers?: array<string, string>, body?: string, truncated?: bool} $response
     */
    public function queueResponse(array $response): void
    {
        $this->responses[] = [
            'status' => $response['status'],
            'headers' => $response['headers'] ?? [],
            'body' => $response['body'] ?? '',
            'truncated' => $response['truncated'] ?? false,
        ];
    }

    public function queueError(string $url, HttpTransportException $e): void
    {
        $this->errorsByUrl[$url] = $e;
    }

    /**
     * @param array{
     *     connect_timeout: int,
     *     read_timeout: int,
     *     max_size: int,
     *     user_agent?: string
     * } $options
     *
     * @return array{status: int, headers: array<string, string>, body: string, truncated: bool}
     */
    public function get(string $url, array $options): array
    {
        $this->requestedUrls[] = $url;

        if (isset($this->errorsByUrl[$url])) {
            throw $this->errorsByUrl[$url];
        }

        $response = array_shift($this->responses);
        if ($response === null) {
            throw new HttpTransportException('FakeTransport: no queued response for ' . $url);
        }

        return $response;
    }

    /** @return list<string> */
    public function getRequestedUrls(): array
    {
        return $this->requestedUrls;
    }
}
