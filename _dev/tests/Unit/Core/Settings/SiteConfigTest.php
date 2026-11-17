<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Settings;

use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\UnknownProjectException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SiteConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function validConfig(): array
    {
        return [
            'domains_app' => [
                'damoony.ru' => [
                    'folder' => 'damoony.ru',
                    'namespace' => 'damoony_ru',
                    'mirrors' => [
                        'damoony.ru' => ['available' => true],
                        'damoony3.ru' => ['available' => true],
                    ],
                ],
            ],
            'alliases' => [
                'damoony.ru' => [
                    'www' => ['available' => true, 'langs' => ['ru', 'en']],
                    'docs' => ['available' => false, 'langs' => ['ru']],
                ],
            ],
        ];
    }

    public function testParsesProject(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame(['damoony.ru'], $site->projectKeys());
        self::assertTrue($site->has('damoony.ru'));
        self::assertFalse($site->has('nope.ru'));
        self::assertSame('damoony.ru', $site->folder('damoony.ru'));
        self::assertSame('damoony_ru', $site->namespace('damoony.ru'));
        self::assertNull($site->project('nope.ru'));
    }

    public function testRequireProjectThrowsForUnknown(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не найден в domains_app');
        $site->requireProject('nope.ru');
    }

    public function testProjectDefaultsForOptionalFields(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());
        $project = $site->requireProject('damoony.ru');

        self::assertSame('www', $project->scheme);
        self::assertSame('maintenance', $project->stub);
        self::assertSame('www', $project->defaultAlias);
        self::assertSame([], $project->langs);
        self::assertSame('', $project->storagePrefix('www'));
        self::assertSame('', $project->storageDb('www'));
        self::assertNull($project->alias('www')?->stub);
        self::assertSame('maintenance', $project->alias('www')?->stubOr('maintenance'));
    }

    public function testProjectOptionalFieldsAreParsed(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['scheme'] = 'landing';
        $data['domains_app']['damoony.ru']['stub'] = 'offline';
        $data['domains_app']['damoony.ru']['default_alias'] = 'docs';
        $data['domains_app']['damoony.ru']['langs'] = ['ru', 'en', 'de'];
        $data['domains_app']['damoony.ru']['aliases_storage'] = [
            'www' => ['db' => 'main', 'prefix' => 'd1_www__'],
        ];
        $data['alliases']['damoony.ru']['www']['stub'] = 'coffee';

        $project = SiteConfig::fromArray($data)->requireProject('damoony.ru');

        self::assertSame('landing', $project->scheme);
        self::assertSame('offline', $project->stub);
        self::assertSame('docs', $project->defaultAlias);
        self::assertSame(['ru', 'en', 'de'], $project->langs);
        self::assertSame('main', $project->storageDb('www'));
        self::assertSame('d1_www__', $project->storagePrefix('www'));
        self::assertSame('coffee', $project->alias('www')?->stub);
        self::assertSame('coffee', $project->alias('www')?->stubOr('maintenance'));
    }

    public function testRejectsInvalidScheme(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['scheme'] = 'Bad-Scheme';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scheme недопустим');
        SiteConfig::fromArray($data);
    }

    public function testRejectsInvalidProjectLangs(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['langs'] = ['ru', 'EN'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('недопустимый код языка');
        SiteConfig::fromArray($data);
    }

    public function testRejectsInvalidStoragePrefix(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['aliases_storage'] = [
            'www' => ['db' => 'main', 'prefix' => 'bad-prefix'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prefix недопустим');
        SiteConfig::fromArray($data);
    }

    public function testRejectsInvalidAliasStub(): void
    {
        $data = self::validConfig();
        $data['alliases']['damoony.ru']['www']['stub'] = 'bad stub';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stub недопустим');
        SiteConfig::fromArray($data);
    }

    public function testResolveAcceptsProjectKey(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame('damoony.ru', $site->resolve('damoony.ru')->key);
        self::assertSame('damoony.ru', $site->resolveFolder('damoony.ru'));
    }

    public function testResolveAcceptsMirrorHost(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame('damoony.ru', $site->resolve('damoony3.ru')->key);
        self::assertSame('damoony.ru', $site->resolveFolder('damoony3.ru'));
        self::assertSame('damoony.ru', $site->resolveFolder('Damoony3.ru:443'));
    }

    public function testResolveThrowsForUnknown(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        $this->expectException(UnknownProjectException::class);
        $this->expectExceptionMessage('не найден в domains_app');
        $site->resolve('nope.ru');
    }

    public function testAliasesBelongToProject(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());
        $project = $site->requireProject('damoony.ru');

        self::assertSame(['www', 'docs'], $project->aliasNames());
        self::assertTrue($project->hasAlias('www'));
        self::assertFalse($project->hasAlias('login'));
        self::assertSame(['ru', 'en'], $project->aliasLangs('www'));
        self::assertSame(['ru'], $project->aliasLangs('docs'));
        self::assertTrue($project->isAliasAvailable('www'));
        self::assertFalse($project->isAliasAvailable('docs'));
        self::assertSame([], $project->aliasLangs('missing'));
    }

    public function testProjectByHostWithMirrors(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame('damoony.ru', $site->projectByHost('damoony.ru')?->key);
        self::assertSame('damoony.ru', $site->projectByHost('damoony3.ru')?->key);
        self::assertSame('damoony.ru', $site->folderByHost('damoony3.ru'));
        self::assertNull($site->projectByHost('unknown.ru'));
        self::assertNull($site->folderByHost('unknown.ru'));
    }

    public function testHostIsNormalized(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame('damoony.ru', SiteConfig::normalizeHost('Damoony.RU'));
        self::assertSame('damoony.ru', SiteConfig::normalizeHost('damoony.ru:8080'));
        self::assertSame('damoony.ru', SiteConfig::normalizeHost('damoony.ru.'));

        self::assertSame('damoony.ru', $site->projectByHost('Damoony3.RU:443')?->folder);
    }

    public function testHostMap(): void
    {
        $site = SiteConfig::fromArray(self::validConfig());

        self::assertSame(
            ['damoony.ru' => 'damoony.ru', 'damoony3.ru' => 'damoony.ru'],
            $site->hostMap(),
        );
    }

    public function testHostAvailability(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['mirrors']['damoony3.ru'] = ['available' => false];
        $site = SiteConfig::fromArray($data);

        self::assertTrue($site->isHostAvailable('damoony.ru'));
        self::assertFalse($site->isHostAvailable('damoony3.ru'));
        self::assertFalse($site->isHostAvailable('unknown.ru'));
    }

    public function testMirrorDefaultsToAvailable(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['mirrors']['noFlag.ru'] = [];
        $site = SiteConfig::fromArray($data);

        self::assertTrue($site->isHostAvailable('noFlag.ru'));
    }

    public function testIpv4MirrorIsAccepted(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['mirrors']['187.188.1.1'] = ['available' => true];
        $site = SiteConfig::fromArray($data);

        self::assertSame('damoony.ru', $site->projectByHost('187.188.1.1')?->key);
        self::assertTrue(SiteConfig::isValidHost('187.188.1.1'));
    }

    public function testRejectsAliasesForUnknownProject(): void
    {
        $data = self::validConfig();
        $data['alliases']['typo.ru'] = ['www' => ['available' => true]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('alliases для неизвестного проекта');
        SiteConfig::fromArray($data);
    }

    public function testRejectsDuplicateHostBetweenProjects(): void
    {
        $data = self::validConfig();
        $data['domains_app']['admin'] = [
            'folder' => 'admin',
            'namespace' => 'admin',
            'mirrors' => ['damoony.ru' => ['available' => true]],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('уже принадлежит проекту');
        SiteConfig::fromArray($data);
    }

    public function testRejectsFolderTraversal(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['folder'] = '../etc';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('folder недопустим');
        SiteConfig::fromArray($data);
    }

    public function testRejectsAbsoluteFolder(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['folder'] = '/app/damoony.ru';

        $this->expectException(RuntimeException::class);
        SiteConfig::fromArray($data);
    }

    public function testRejectsMissingNamespace(): void
    {
        $data = self::validConfig();
        unset($data['domains_app']['damoony.ru']['namespace']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('namespace недопустим');
        SiteConfig::fromArray($data);
    }

    public function testRejectsInvalidNamespace(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['namespace'] = 'Damoony-Ru';

        $this->expectException(RuntimeException::class);
        SiteConfig::fromArray($data);
    }

    public function testRejectsHostWithoutTld(): void
    {
        $data = self::validConfig();
        $data['domains_app']['damoony.ru']['mirrors']['admin'] = ['available' => true];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('недопустимый хост');
        SiteConfig::fromArray($data);
    }

    public function testAllowsEmptyDomainsApp(): void
    {
        $site = SiteConfig::fromArray(['domains_app' => [], 'alliases' => []]);

        self::assertSame([], $site->projectKeys());
        self::assertSame([], $site->hostMap());
        self::assertNull($site->projectByHost('any.ru'));
    }

    public function testRejectsMissingDomainsApp(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('отсутствует domains_app');
        SiteConfig::fromArray(['alliases' => []]);
    }

    public function testRejectsOldSchema(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('domains_app');
        SiteConfig::fromArray([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_mirrors' => ['example.ru' => 'example.ru'],
        ]);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('некорректный JSON');
        SiteConfig::fromJson('{not json');
    }

    public function testRejectsTrailingCommaJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('некорректный JSON');
        SiteConfig::fromJson('{"domains_app": {"a.ru": {}},}');
    }

    public function testFromFileThrowsWhenMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('не найден');
        SiteConfig::fromFile(__DIR__ . '/does-not-exist.config');
    }

    public function testServiceKeysAreIgnored(): void
    {
        $data = self::validConfig();
        $data['_readme'] = 'документация';
        $data['domains_app']['_note'] = 'служебный ключ';
        $data['alliases']['_note'] = 'служебный ключ';

        $site = SiteConfig::fromArray($data);

        self::assertSame(['damoony.ru'], $site->projectKeys());
    }

    public function testRepoConfigIsValid(): void
    {
        $path = dirname(__DIR__, 5) . '/config/site.config';
        if (!is_file($path)) {
            self::markTestSkipped('config/site.config отсутствует (локальный конфиг не в git)');
        }

        $site = SiteConfig::fromFile($path);

        self::assertTrue($site->has('damoony.ru'));
        self::assertSame('damoony.ru', $site->folderByHost('damoony3.ru'));
        self::assertSame('damoony_ru', $site->namespace('damoony.ru'));
    }
}
