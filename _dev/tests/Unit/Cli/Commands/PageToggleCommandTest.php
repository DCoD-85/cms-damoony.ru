<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageToggleCommandTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pt_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['damoony.ru' => 'damoony_ru'],
            'domains_mirrors' => ['damoony.ru' => 'damoony.ru', 'damoony3.ru' => 'damoony.ru'],
            'alliases' => ['damoony.ru' => ['www' => ['available' => true]]],
        ]));

        $algDir = $this->root . '/app/damoony.ru/www/algoritm';
        mkdir($algDir . '/about', 0o777, true);
        file_put_contents($algDir . '/about/algoritm_about.php', '<?php');
        file_put_contents($algDir . '/about/algoritm_about.rules.json', json_encode(['auth' => false]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testTogglesFlagWithHostDomain(): void
    {
        $res = new PageToggleCommand($this->paths)->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->ctx(false),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertTrue($res->data['value']);

        $rules = json_decode((string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json'), true);
        self::assertTrue($rules['auth']);
    }

    public function testTogglesFlagWithMirrorDomain(): void
    {
        $res = new PageToggleCommand($this->paths)->run(
            ['domain' => 'damoony3.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->ctx(false),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
    }

    public function testDryRunDoesNotModifyFile(): void
    {
        $res = new PageToggleCommand($this->paths)->run(
            ['domain' => 'damoony.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->ctx(true), // isDryRun = true
        );

        self::assertTrue($res->ok);
        self::assertTrue($res->data['dry_run']);

        $rules = json_decode((string) file_get_contents($this->root . '/app/damoony.ru/www/algoritm/about/algoritm_about.rules.json'), true);
        self::assertFalse($rules['auth']); // still false
    }

    private function ctx(bool $isDryRun): Context
    {
        return new Context(static fn (): null => null, false, $isDryRun);
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
}
