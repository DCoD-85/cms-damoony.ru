<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\I18n;

use Damoony\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/lang_' . uniqid('', true);
        mkdir($this->langDir, 0o775, true);

        file_put_contents(
            $this->langDir . '/ru.php',
            "<?php\nreturn [\n    'test.key' => 'Привет',\n    'test.param' => 'Привет, {name}!',\n    'only.ru' => 'Только РУ',\n];\n",
        );
        file_put_contents(
            $this->langDir . '/en.php',
            "<?php\nreturn [\n    'test.key' => 'Hello',\n    'test.param' => 'Hello, {name}!',\n    'only.en' => 'EN only',\n];\n",
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->langDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->langDir);
    }

    public function testRuTranslation(): void
    {
        $t = new Translator('ru', 'ru', $this->langDir);
        self::assertSame('Привет', $t->t('test.key'));
    }

    public function testEnTranslation(): void
    {
        $t = new Translator('en', 'ru', $this->langDir);
        self::assertSame('Hello', $t->t('test.key'));
    }

    public function testParamsInterpolation(): void
    {
        $t = new Translator('en', 'ru', $this->langDir);
        self::assertSame('Hello, admin!', $t->t('test.param', ['name' => 'admin']));
    }

    public function testMissingKeyReturnsKey(): void
    {
        $t = new Translator('en', 'ru', $this->langDir);
        self::assertSame('nonexistent.key', $t->t('nonexistent.key'));
    }

    public function testFallbackForMissingKeyInCurrent(): void
    {
        $t = new Translator('en', 'ru', $this->langDir);
        self::assertSame('Только РУ', $t->t('only.ru'));
    }

    public function testCurrentReturnsCode(): void
    {
        self::assertSame('en', (new Translator('en', 'ru', $this->langDir))->current());
        self::assertSame('ru', (new Translator('ru', 'ru', $this->langDir))->current());
    }

    public function testInvalidCurrentFallsBackToFallback(): void
    {
        $t = new Translator('xx', 'ru', $this->langDir);
        self::assertSame('Привет', $t->t('test.key'));
    }

    public function testRealLangFiles(): void
    {
        $t = new Translator('en', 'ru');
        self::assertSame('Domains', $t->t('admin.nav.domains'));
        self::assertSame('Домены', (new Translator('ru', 'ru'))->t('admin.nav.domains'));
    }
}
