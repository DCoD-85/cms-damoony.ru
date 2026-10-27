<?php

declare(strict_types=1);

namespace Damoony\Error;

use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Document;

final class ErrorPage
{
    public static function notFound(string $lang = 'ru'): Response
    {
        return self::html(404, '', '', $lang);
    }

    public static function forbidden(string $lang = 'ru'): Response
    {
        return self::html(403, '', '', $lang);
    }

    public static function serverError(string $lang = 'ru', string $detail = ''): Response
    {
        return self::html(500, '', $detail, $lang);
    }

    public static function maintenance(string $lang = 'ru'): Response
    {
        return self::html(503, '', '', $lang);
    }

    public static function tooManyRequests(string $lang = 'ru', int $retryAfter = 60): Response
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));
        $message = "Пожалуйста, подождите {$minutes} мин. перед повторной попыткой.";

        return self::html(429, 'Слишком много запросов', $message, $lang)
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    public static function html(int $code, string $title = '', string $message = '', string $lang = 'ru'): Response
    {
        $title = $title !== '' ? $title : self::defaultTitle($code);
        $message = $message !== '' ? $message : self::defaultMessage($code);

        $doc = new Document();
        $doc->lang($lang);
        $doc->title($code . ' ' . $title);

        $wrap = Builder::div('error error-' . $code);
        $wrap->append(Builder::h(1, 'error-head')->content((string) $code));
        $wrap->append(Builder::p('error-title')->content($title));
        if ($message !== '') {
            $wrap->append(Builder::p('error-message')->content($message));
        }

        $doc->body()->append($wrap);

        return Response::html($doc->release(), $code);
    }

    private static function defaultTitle(int $code): string
    {
        return match ($code) {
            403 => 'Доступ запрещён',
            404 => 'Страница не найдена',
            429 => 'Слишком много запросов',
            500 => 'Ошибка сервера',
            503 => 'Технические работы',
            default => 'Ошибка',
        };
    }

    private static function defaultMessage(int $code): string
    {
        return match ($code) {
            403 => 'У вас нет доступа к этой странице.',
            404 => 'Запрошенная страница не существует.',
            429 => 'Вы сделали слишком много запросов за короткое время.',
            500 => 'Что-то пошло не так. Попробуйте позже.',
            503 => 'Сайт временно на обслуживании.',
            default => '',
        };
    }
}
