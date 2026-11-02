<?php

declare(strict_types=1);

namespace Damoony\Ai;

/**
 * Граф состояний для AI и UI (HATEOAS).
 */
final class Navigator
{
    /** @var list<string> */
    private const OFFERABLE = [
        'domain:new',
        'allias:new',
        'skin:new',
        'skin:edit',
        'page:new',
        'page:write',
        'page:read',
        'image:search',
        'image:download',
        'done',
    ];

    /**
     * @param array{domains?: list<string>} $state
     *
     * @return list<array{cmd: string, args: array<string, mixed>, hint: string}>
     */
    public static function start(array $state): array
    {
        $domains = $state['domains'] ?? [];
        $next = [];

        if ($domains === []) {
            $next[] = self::item(
                'domain:new',
                ['domain' => '<new_fqdn>'],
                'Нет ни одного домена. Создайте первый: domain:new {domain: "<FQDN>"}',
            );
        } else {
            $next[] = self::item(
                'allias:new',
                ['domain' => '<choose from state.domains>', 'name' => '<lowercase_name>'],
                'Создать алиас в существующем домене (см. state.domains)',
            );
            $next[] = self::item(
                'domain:new',
                ['domain' => '<new_fqdn>'],
                'Создать новый домен',
            );
        }

        return $next;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<array{cmd: string, args: array<string, mixed>, hint: string}>
     */
    public static function after(string $cmd, array $args): array
    {
        return match ($cmd) {
            'domain:new' => [
                self::item(
                    'allias:new',
                    ['domain' => (string) ($args['domain'] ?? ''), 'name' => '<lowercase_name>'],
                    'Создать первый алиас в этом домене',
                ),
                self::item('done', [], 'Завершить (если больше ничего не нужно)'),
            ],
            'allias:new' => [
                self::item(
                    'skin:new',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['name'] ?? ''),
                        'name' => 'default',
                    ],
                    'Создать скин по умолчанию для алиаса',
                ),
                self::item(
                    'page:new',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['name'] ?? ''),
                        'name' => '<page_name>',
                    ],
                    'Создать страницу в этом алиасе',
                ),
                self::item('done', [], 'Завершить'),
            ],
            'skin:new' => [
                self::item(
                    'skin:edit',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => (string) ($args['name'] ?? 'default'),
                        'file' => 'css/main.css',
                        'content' => '',
                    ],
                    'Заполнить CSS скина',
                ),
                self::item(
                    'page:new',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => '<page_name>',
                    ],
                    'Создать страницу',
                ),
                self::item('done', [], 'Завершить'),
            ],
            'page:new' => [
                self::item(
                    'page:write',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => (string) ($args['name'] ?? ''),
                        'blocks' => [],
                    ],
                    'Наполнить страницу контентом (JSON-блоки)',
                ),
                self::item(
                    'image:search',
                    ['query' => '<search_query>', 'count' => 3],
                    'Найти картинку для страницы (опционально)',
                ),
                self::item('done', [], 'Завершить'),
            ],
            'image:search' => [
                self::item(
                    'image:download',
                    [
                        'url' => '<url_from_results>',
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'category' => 'general',
                        'alt' => '',
                    ],
                    'Скачать картинку локально и получить local_url',
                ),
            ],
            'image:download' => [
                self::item(
                    'page:write',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => (string) ($args['name'] ?? ''),
                        'blocks' => [],
                    ],
                    'Вставить local_url в page:write (тип "img")',
                ),
            ],
            'page:write' => [
                self::item(
                    'page:new',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => '<next_page_name>',
                    ],
                    'Создать ещё одну страницу в этом же алиасе',
                ),
                self::item(
                    'page:read',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => (string) ($args['name'] ?? ''),
                    ],
                    'Проверить, что записалось',
                ),
                self::item('done', [], 'Завершить'),
            ],
            'page:read' => [
                self::item(
                    'page:write',
                    [
                        'domain' => (string) ($args['domain'] ?? ''),
                        'allias' => (string) ($args['allias'] ?? ''),
                        'name' => (string) ($args['name'] ?? ''),
                        'blocks' => [],
                    ],
                    'Перезаписать страницу новыми блоками',
                ),
                self::item('done', [], 'Завершить'),
            ],
            'done' => [],
            default => [],
        };
    }

    /** @return list<string> */
    public static function whitelist(): array
    {
        return self::OFFERABLE;
    }

    public static function isOfferable(string $cmd): bool
    {
        return in_array($cmd, self::OFFERABLE, true);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array{cmd: string, args: array<string, mixed>, hint: string}
     */
    private static function item(string $cmd, array $args, string $hint): array
    {
        return ['cmd' => $cmd, 'args' => $args, 'hint' => $hint];
    }
}
