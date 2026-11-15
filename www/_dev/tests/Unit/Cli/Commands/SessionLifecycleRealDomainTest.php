<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\SessionApplyCommand;
use Damoony\Cli\Commands\SessionDiscardCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SessionLifecycleRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_sesslife_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testApplyFindsSessionByRealDomain(): void
    {
        // production + session в .sess_*.real.ru (как создаёт SessionStart)
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // prod\n");

        $sessRoot = $this->root . '/app/.sess_abcdef01.real.ru';
        mkdir($sessRoot . '/www/algoritm', 0o775, true);
        file_put_contents($sessRoot . '/www/algoritm/algoritm.php', "<?php // sess\n");
        file_put_contents(
            $sessRoot . '/.session.json',
            json_encode([
                'tab_id' => 'abcdef01',
                'domain' => 'alias.ru',
                'real_domain' => 'real.ru',
                'production_hash' => 'deliberately-wrong-hash',
                'permissions' => [],
            ], JSON_THROW_ON_ERROR),
        );

        // --domain=alias.ru: apply должен найти сессию в .sess_*.real.ru
        new SessionApplyCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'tab' => 'abcdef01', 'admin-sess' => 'admin'],
            $this->ctx(),
        );

        // Ключевой assert: не "Session not found" (это означало бы, что realDomain не резолвится)
        $this->assertDirectoryExists($sessRoot, 'сессия не должна быть тронута (hash mismatch)');
        $this->assertFileExists(
            $this->root . '/app/real.ru/www/algoritm/algoritm.php',
            'production не должен быть тронут (hash mismatch)',
        );
    }

    public function testDiscardMovesRealDomainSessionToTrash(): void
    {
        $sessRoot = $this->root . '/app/.sess_abcdef01.real.ru';
        mkdir($sessRoot . '/www/algoritm', 0o775, true);
        file_put_contents($sessRoot . '/www/algoritm/algoritm.php', "<?php // sess\n");
        file_put_contents(
            $sessRoot . '/.session.json',
            json_encode(['tab_id' => 'abcdef01'], JSON_THROW_ON_ERROR),
        );

        new SessionDiscardCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'tab' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($sessRoot, 'сессия должна быть перемещена в trash');
        $trashEntries = glob($this->root . '/app/_trash_sess_abcdef01_real.ru_*') ?: [];
        $this->assertNotEmpty($trashEntries, 'в trash должен быть _trash_sess_*.real.ru');
    }

    public function testApplyUnknownDomainRejected(): void
    {
        new SessionApplyCommand($this->paths)->run(
            ['domain' => 'unknown.ru', 'tab' => 'abcdef01', 'admin-sess' => 'admin'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/app/_trash_sess_abcdef01_unknown.ru_*');
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
