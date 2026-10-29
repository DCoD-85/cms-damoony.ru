<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Error;

use Damoony\Error\ErrorPage;
use PHPUnit\Framework\TestCase;

final class ErrorPageTest extends TestCase
{
    public function testNotFoundRendersLocalizedRu(): void
    {
        $r = ErrorPage::notFound('ru');

        self::assertSame(404, $r->status);
        self::assertStringContainsString('Страница не найдена', $r->body);
        self::assertStringContainsString('Запрошенная страница не существует.', $r->body);
        self::assertStringContainsString('lang="ru"', $r->body);
    }

    public function testServerErrorRendersEnglish(): void
    {
        $r = ErrorPage::serverError('en');

        self::assertSame(500, $r->status);
        self::assertStringContainsString('Server error', $r->body);
        self::assertStringContainsString('Something went wrong', $r->body);
        self::assertStringContainsString('lang="en"', $r->body);
    }

    public function testServerErrorWithDetailOverridesMessage(): void
    {
        $r = ErrorPage::serverError('ru', 'DB is down');

        self::assertSame(500, $r->status);
        self::assertStringContainsString('DB is down', $r->body);
        self::assertStringNotContainsString('Что-то пошло не так', $r->body);
    }

    public function testTooManyRequestsSubstitutesMinutes(): void
    {
        $r = ErrorPage::tooManyRequests('ru', 180);

        self::assertSame(429, $r->status);
        self::assertSame('180', $r->headers['retry-after'] ?? null);
        self::assertStringContainsString('3 мин', $r->body);
    }

    public function testUnknownCodeFallsBackToDefault(): void
    {
        $r = ErrorPage::html(418, '', '', 'en');

        self::assertSame(418, $r->status);
        self::assertStringContainsString('Error', $r->body);
    }

    public function testMaintenanceRendersChinese(): void
    {
        $r = ErrorPage::maintenance('zh');

        self::assertSame(503, $r->status);
        self::assertStringContainsString('维护中', $r->body);
        self::assertStringContainsString('lang="zh"', $r->body);
    }
}
