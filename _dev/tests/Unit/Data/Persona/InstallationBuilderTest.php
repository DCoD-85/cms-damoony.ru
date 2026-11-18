<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Persona;

use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\InstallationBuilder;
use Damoony\Data\Persona\PersonaRepository;
use PHPUnit\Framework\TestCase;

final class InstallationBuilderTest extends TestCase
{
    private string $root;
    private PersonaRepository $repo;
    private int $installationId;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_build_test_' . bin2hex(random_bytes(6));

        foreach ([
            'bin',
            'vendor',
            'src',
            'www',
            'migrations',
            'stubs/assemblies/tiny/app/.blocksglobal/top',
            'stubs/assemblies/tiny/app/.i18nglobal',
            'stubs/assemblies/tiny/app/news/algoritm',
            'stubs/assemblies/tiny/media/www/skin/light/css',
            'stubs/assemblies/tiny/media/news',
            'stubs/assemblies/tiny/shared/css',
        ] as $dir) {
            mkdir($this->root . '/' . $dir, 0o775, true);
        }

        file_put_contents($this->root . '/bin/cli.php', '<?php // cli');
        file_put_contents($this->root . '/vendor/autoload.php', '<?php // autoload');
        file_put_contents($this->root . '/src/Foo.php', '<?php // foo');
        file_put_contents($this->root . '/www/index.php', '<?php // index');
        file_put_contents($this->root . '/migrations/001_x.sql', 'CREATE TABLE x (id INT);');

        file_put_contents(
            $this->root . '/stubs/assemblies/tiny/assembly.json',
            json_encode([
                'name' => 'Tiny',
                'base_lang' => 'ru',
                'aliases' => ['www', 'news'],
                'langs' => ['project' => ['ru', 'en'], 'default' => 'ru', 'aliases' => ['www' => ['ru', 'en'], 'news' => ['ru']]],
            ]),
        );
        file_put_contents(
            $this->root . '/stubs/assemblies/tiny/app/.blocksglobal/top/top.php',
            "<?php\nnamespace {{namespace}};\nfinal class blocksglobal_top {}\n",
        );
        file_put_contents($this->root . '/stubs/assemblies/tiny/app/.i18nglobal/ru.json', '{"a":"А"}');
        file_put_contents($this->root . '/stubs/assemblies/tiny/app/.i18nglobal/en.json', '{"a":"A"}');
        file_put_contents($this->root . '/stubs/assemblies/tiny/app/news/algoritm/algoritm.php', '<?php // news');
        file_put_contents($this->root . '/stubs/assemblies/tiny/media/www/skin/light/css/main.css', '.x{}');
        file_put_contents($this->root . '/stubs/assemblies/tiny/media/news/news.css', '.n{}');
        file_put_contents($this->root . '/stubs/assemblies/tiny/shared/css/elements.css', '.ui{}');

        $conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $conn->pdo();
        $pdo->exec('CREATE TABLE persona_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT, status TEXT NOT NULL DEFAULT "pending", email_verified_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE installations (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, domain TEXT NOT NULL, assembly TEXT NOT NULL, langs TEXT NOT NULL, aliases TEXT, status TEXT NOT NULL DEFAULT "pending", token_hash TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, claimed_at TEXT, expires_at TEXT, install_count INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE builds (id INTEGER PRIMARY KEY AUTOINCREMENT, installation_id INTEGER NOT NULL, version TEXT NOT NULL, file TEXT NOT NULL, sha256 TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TEXT NOT NULL, download_count INTEGER NOT NULL DEFAULT 0)');

        $this->repo = new PersonaRepository($conn);
        $account = $this->repo->createAccount('build@example.test', 'hash', 'active');
        $this->installationId = $this->repo->createInstallation(
            $account,
            'site.test',
            'tiny',
            ['ru', 'en'],
            hash('sha256', 'token'),
            'active',
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testBuildsBundleWithProjectAndConfig(): void
    {
        $builder = new InstallationBuilder(new Paths($this->root), $this->repo);
        $build = $builder->build($this->repo->installationById($this->installationId));

        self::assertSame('install-' . $this->installationId . '-', substr((string) $build['file'], 0, strlen('install-' . $this->installationId . '-')));
        self::assertSame(64, strlen((string) $build['sha256']));
        self::assertFileExists($builder->filePath((string) $build['file']));

        $phar = new \PharData($builder->filePath((string) $build['file']));
        self::assertTrue(isset($phar['bin/cli.php']));
        self::assertTrue(isset($phar['app/site.test/.blocksglobal/top/top.php']));
        self::assertTrue(isset($phar['www/media/site.test/www/skin/light/css/main.css']));
        self::assertTrue(isset($phar['config/site.config']));

        $project = $phar['app/site.test/.blocksglobal/top/top.php']->getContent();
        self::assertStringContainsString('namespace site_test;', $project);

        $config = json_decode($phar['config/site.config']->getContent(), true);
        self::assertSame('site.test', (array_key_first($config['domains_app'])));
        self::assertSame(['ru', 'en'], $config['domains_app']['site.test']['langs']);
        self::assertArrayHasKey('www', $config['domains_app']['site.test']['aliases']);
    }

    public function testSecondBuildReusesArchive(): void
    {
        $builder = new InstallationBuilder(new Paths($this->root), $this->repo);
        $installation = $this->repo->installationById($this->installationId);

        $first = $builder->build($installation);
        $second = $builder->build($installation);

        self::assertSame($first['id'], $second['id']);
    }

    public function testSelectedAliasesAndLangsOnly(): void
    {
        $account = $this->repo->accountByEmail('build@example.test');
        $installationId = $this->repo->createInstallation(
            (int) $account['id'],
            'tiny.test',
            'tiny',
            ['ru'],
            hash('sha256', 'token-2'),
            'active',
            ['www'],
        );

        $builder = new InstallationBuilder(new Paths($this->root), $this->repo);
        $build = $builder->build($this->repo->installationById($installationId));

        $phar = new \PharData($builder->filePath((string) $build['file']));
        self::assertTrue(isset($phar['app/tiny.test/.blocksglobal/top/top.php']));
        self::assertFalse(isset($phar['app/tiny.test/news/algoritm/algoritm.php']));
        self::assertFalse(isset($phar['www/media/tiny.test/news/news.css']));
        self::assertFalse(isset($phar['app/tiny.test/.i18nglobal/en.json']));
        self::assertTrue(isset($phar['app/tiny.test/.i18nglobal/ru.json']));

        $config = json_decode($phar['config/site.config']->getContent(), true);
        self::assertSame(['www'], array_keys($config['domains_app']['tiny.test']['aliases']));
        self::assertSame(['ru'], $config['domains_app']['tiny.test']['langs']);
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
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
