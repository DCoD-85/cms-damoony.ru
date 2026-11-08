<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\BlockEditCommand;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class BlockEditCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_be_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/.alliasglobal', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function ctx(Result $r): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = '') use ($r): void {
            $r->step($m, $ok, $d);
        });
    }

    private function createBlock(string $name = 'top'): void
    {
        new BlockNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => $name], $this->ctx(new Result()));
    }

    private function enablePhp(): void
    {
        file_put_contents(
            $this->root . '/config/engine.config',
            json_encode(['security' => ['allow_php_blocks' => true]]),
        );
    }

    // --- S80: rules ---

    public function testS80UpdatesRules(): void
    {
        $this->createBlock();
        $paths = new Paths($this->root);
        $res = new BlockEditCommand($paths)->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true,"type":"visual"}',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $rules = json_decode((string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top.rules.json',
        ), true);
        self::assertSame('visual', $rules['type']);
    }

    public function testS80UpdatesRulesViaBase64(): void
    {
        $this->createBlock();
        $payload = base64_encode('{"enabled":true,"type":"script"}');
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content_b64' => $payload,
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $rules = json_decode((string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top.rules.json',
        ), true);
        self::assertSame('script', $rules['type']);
    }

    public function testS80RejectsInvalidBase64(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content_b64' => '!!!not-base64!!!',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('base64', $res->errors[0]);
    }

    public function testS80RejectsEmptyContent(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--content', $res->errors[0]);
    }

    public function testS80RejectsInvalidJson(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => '{not-json',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('JSON', $res->errors[0]);
    }

    public function testS80RejectsInvalidType(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true,"type":"custom"}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('type', $res->errors[0]);
    }

    public function testS80RejectsOversizeRules(): void
    {
        $this->createBlock();
        $payload = '{"type":"visual","pad":"' . str_repeat('a', 70000) . '"}';
        self::assertGreaterThan(65536, strlen($payload));

        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => $payload,
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('байт', $res->errors[0]);
    }

    // --- S80: editor ---

    public function testS80UpdatesEditor(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'editor',
            'content' => '[{"id":"a","type":"text"}]',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $editor = json_decode((string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top.editor.json',
        ), true);
        self::assertSame('text', $editor[0]['type']);
    }

    // --- S80: code (raw PHP) ---

    public function testS80RejectsCodeWhenAllowPhpBlocksFalse(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'code',
            'content' => '<?php echo "x";',
            'allow_php' => '1',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('allow_php_blocks', $res->errors[0]);
    }

    public function testS80RejectsCodeWithoutAllowPhpFlag(): void
    {
        $this->createBlock();
        $this->enablePhp();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'code',
            'content' => '<?php echo "x";',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--allow_php', $res->errors[0]);
    }

    public function testS80RejectsCodeWithoutPhpOpenTag(): void
    {
        $this->createBlock();
        $this->enablePhp();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'code',
            'content' => 'echo "x";',
            'allow_php' => '1',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('<?php', $res->errors[0]);
    }

    public function testS80AcceptsCodeWithAllFlags(): void
    {
        $this->createBlock();
        $this->enablePhp();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'code',
            'content' => '<?php echo "custom";',
            'allow_php' => '1',
        ], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $code = (string) file_get_contents(
            $this->root . '/app/example.ru/.alliasglobal/top/top.php',
        );
        self::assertStringContainsString('custom', $code);
    }

    // --- S80: домен / блок / file ---

    public function testS80RejectsInvalidFile(): void
    {
        $this->createBlock();
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'top',
            'file' => 'shell',
            'content' => '{}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Недопустимый файл', $res->errors[0]);
    }

    public function testS80RejectsMissingBlock(): void
    {
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'ghost',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Блок не найден', $res->errors[0]);
    }

    public function testS80RejectsMissingDomain(): void
    {
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'other.ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Домен не найден', $res->errors[0]);
    }

    public function testS80RejectsPathTraversal(): void
    {
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'a..ru',
            'name' => 'top',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }

    public function testS80RejectsInvalidBlockName(): void
    {
        $res = new BlockEditCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'name' => 'BadName',
            'file' => 'rules',
            'content' => '{"enabled":true}',
        ], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }
}
