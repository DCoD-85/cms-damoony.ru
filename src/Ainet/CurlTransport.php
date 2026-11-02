<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Единственная реализация HttpTransportInterface — curl.
 * Поддерживает GET и HEAD.
 */
final class CurlTransport implements HttpTransportInterface
{
    public function get(string $url, array $options): array
    {
        $ch = curl_init();

        $connectTimeout = $options['connect_timeout'];
        $readTimeout = $options['read_timeout'];
        $maxSize = $options['max_size'];
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        $userAgent = $options['user_agent'] ?? 'Damoony-AINET/1.0';

        $headers = [];
        $body = '';
        $truncated = false;

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $readTimeout,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $len = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return $len;
                }
                if (preg_match('/^HTTP\/\S+\s+\d+/', $trimmed) === 1) {
                    return $len;
                }
                $parts = explode(':', $trimmed, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $len;
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, &$truncated, $maxSize): int {
                $body .= $chunk;
                if (strlen($body) > $maxSize) {
                    $truncated = true;
                    return 0;
                }

                return strlen($chunk);
            },
        ];

        if ($method === 'HEAD') {
            $curlOptions[CURLOPT_NOBODY] = true;
        } else {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // CURLE_WRITE_ERROR (23) при превышении max_size — не ошибка, а truncated
        if ($ok === false && !($errno === 23 && $truncated)) {
            throw new HttpTransportException(
                "Curl error ({$errno}): {$error}",
                $errno,
            );
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
            'truncated' => $truncated,
        ];
    }
}