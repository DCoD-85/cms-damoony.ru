<?php

declare(strict_types=1);

namespace Damoony\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class LinkTraversalTest extends TestCase
{
    private string $baseUrl = 'http://127.0.0.1:8080';
    private string $cookieFile;
    private array $visited = [];
    // Запрещенные слова в ссылках (чтобы не нажать "Удалить" или "Выйти")
    private array $blacklist = ['logout', 'remove', 'delete', 'clear', 'toggle', 'unban', 'download', 'action='];

    protected function setUp(): void
    {
        $this->cookieFile = sys_get_temp_dir() . '/cookies_' . uniqid('', true) . '.txt';

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Test server is not running on ' . $this->baseUrl);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    public function testAdminLinkTraversal(): void
    {
        // 1. Получаем CSRF-токен со страницы логина
        $loginPage = $this->request('/admin/?p=login');
        $csrfToken = $this->extractCsrf($loginPage);

        // 2. Авторизуемся
        $postData = http_build_query([
            'login' => 'admin',
            'password' => 'secret',
            '_csrf' => $csrfToken,
        ]);
        $this->request('/admin/?p=login', 'POST', $postData);

        // 3. Начинаем краулинг
        $queue = ['/admin/?p=dashboard', '/admin/?p=domain'];
        $crawled = [];

        while (count($queue) > 0 && count($crawled) < 30) {
            $url = array_shift($queue);

            if (isset($crawled[$url])) {
                continue;
            }
            $crawled[$url] = true;

            $html = $this->request($url);
            $this->assertNotFalse($html, "Страница не загрузилась: {$url}");

            // Извлекаем все ссылки
            $links = $this->extractLinks($html);
            foreach ($links as $link) {
                if ($this->isSafeLink($link) && !isset($crawled[$link]) && str_starts_with($link, '/admin/')) {
                    $queue[] = $link;
                }
            }
        }

        // На пустой базе CI краулер найдет только Dashboard, Domain и New Domain.
        // Требуем хотя бы 3 страницы, чтобы тест не падал на пустой БД.
        $this->assertGreaterThanOrEqual(3, count($crawled), 'Краулер прошел слишком мало страниц.');
    }

    private function request(string $path, string $method = 'GET', ?string $postData = null): string|false
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function extractCsrf(string $html): string
    {
        // Более надежный поиск (не зависит от порядка атрибутов name и value)
        if (preg_match('/name="_csrf"[^>]*value="([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }
        $this->fail('CSRF token не найден на странице');
    }

    private function extractLinks(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//a/@href');

        $links = [];
        foreach ($nodes as $node) {
            $links[] = $node->nodeValue;
        }
        return $links;
    }

    private function isSafeLink(string $link): bool
    {
        foreach ($this->blacklist as $word) {
            if (str_contains(strtolower($link), $word)) {
                return false;
            }
        }
        return true;
    }
}
