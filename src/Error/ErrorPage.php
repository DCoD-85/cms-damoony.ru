<?php

declare(strict_types=1);

namespace Damoony\Error;

use Damoony\Http\Response;
use Damoony\I18n\Translator;
use Damoony\Ui\Builder;
use Damoony\Ui\Document;

final class ErrorPage
{
    /** @var array<string, Translator> */
    private static array $translators = [];

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
        $t = self::translator($lang);

        return self::html(429, $t->t('error.429.title'), $t->t('error.429.retry', ['minutes' => $minutes]), $lang)
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    public static function html(int $code, string $title = '', string $message = '', string $lang = 'ru'): Response
    {
        $t = self::translator($lang);
        $title = $title !== '' ? $title : self::defaultTitle($code, $t);
        $message = $message !== '' ? $message : self::defaultMessage($code, $t);

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

    private static function translator(string $lang): Translator
    {
        return self::$translators[$lang] ??= new Translator($lang, 'ru');
    }

    private static function defaultTitle(int $code, Translator $t): string
    {
        $key = 'error.' . $code . '.title';
        $v = $t->t($key);

        return $v === $key ? $t->t('error.default.title') : $v;
    }

    private static function defaultMessage(int $code, Translator $t): string
    {
        $key = 'error.' . $code . '.message';
        $v = $t->t($key);

        return $v === $key ? $t->t('error.default.message') : $v;
    }
}
