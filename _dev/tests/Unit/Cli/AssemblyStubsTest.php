<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;

/**
 * Проверка сборок stubs/assemblies: PHP-синтаксис после подстановки плейсхолдеров,
 * валидность JSON и правило 3 файлов у страниц/блоков.
 */
final class AssemblyStubsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/damoony_asm_' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    public function testAssemblyManifests(): void
    {
        $assemblies = glob(dirname(__DIR__, 4) . '/stubs/assemblies/*', GLOB_ONLYDIR) ?: [];
        self::assertNotEmpty($assemblies);

        foreach ($assemblies as $dir) {
            $manifest = json_decode((string) file_get_contents($dir . '/assembly.json'), true);
            self::assertIsArray($manifest, 'assembly.json: ' . $dir);
            self::assertArrayHasKey('name', $manifest);
        }
    }

    public function testPhpFilesParseAfterSubstitution(): void
    {
        foreach ($this->assemblies() as $name => $dir) {
            foreach ($this->files($dir, '.php') as $file) {
                $code = $this->substitute((string) file_get_contents($file));
                $tmp = $this->tmp . '/' . $name . '_' . md5($file) . '.php';
                file_put_contents($tmp, $code);

                $output = [];
                $code = 0;
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);

                self::assertSame(0, $code, $file . "\n" . implode("\n", $output));
            }
        }
    }

    public function testJsonFilesAreValid(): void
    {
        foreach ($this->assemblies() as $dir) {
            foreach ($this->files($dir, '.json') as $file) {
                json_decode((string) file_get_contents($file), true);
                self::assertSame(JSON_ERROR_NONE, json_last_error(), $file);
            }
        }
    }

    public function testThreeFileRule(): void
    {
        foreach ($this->assemblies() as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $isPage = preg_match('#/algoritm/(?:[a-z0-9_]+/)*algoritm(?:_[a-z0-9_]+)?\.php$#', $path) === 1;
                $isBlock = preg_match('#/\.blocks(global|local)/[^/]+/[^/]+\.php$#', $path) === 1;
                if (!$isPage && !$isBlock) {
                    continue;
                }

                $base = substr($path, 0, -4);
                self::assertFileExists($base . '.rules.json', 'нет rules.json для ' . $path);
                self::assertFileExists($base . '.editor.json', 'нет editor.json для ' . $path);
            }
        }
    }

    /** @return array<string, string> */
    private function assemblies(): array
    {
        $out = [];
        foreach (glob(dirname(__DIR__, 4) . '/stubs/assemblies/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $out[basename($dir)] = $dir;
        }

        return $out;
    }

    /** @return list<string> */
    private function files(string $dir, string $extension): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === ltrim($extension, '.')) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function substitute(string $code): string
    {
        return str_replace(
            ['{{namespace}}', '{{real}}', '{{alias}}', '{{domain}}', '{{timestamp}}'],
            ['test_ns', 'test.ru', 'www', 'test.ru', '1700000000'],
            $code,
        );
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
