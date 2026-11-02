<?php

declare(strict_types=1);

namespace Damoony\Ai;

final class Roles
{
    public const PARSER = 'parser';
    public const INSPECTOR = 'inspector';
    public const PLANNER = 'planner';
    public const ARCHITECT = 'architect';
    public const EXECUTOR = 'executor';
    public const DESIGNER = 'designer';
    public const BUNDLER = 'bundler';
    public const CORRECTOR = 'corrector';
    public const REPORTER = 'reporter';

    /** @var array<string, list<string>> */
    private const MAP = [
        self::PARSER => [
            'domain:list', 'allias:list', 'page:list', 'scheme:list', 'block:list', 'filler:list',
        ],
        self::INSPECTOR => [
            'domain:list', 'allias:list', 'page:list', 'page:read',
            'scheme:list', 'block:list', 'filler:list',
            'structure:check',
        ],
        self::PLANNER => [
            'domain:list', 'allias:list', 'page:list', 'page:read',
            'structure:check',
        ],
        self::ARCHITECT => [
            'domain:new', 'allias:new',
            'block:new', 'scheme:new', 'filler:new',
            'block:edit', 'filler:edit',
            'skin:new', 'skin:list',
            'domain:list', 'allias:list', 'scheme:list', 'block:list',
        ],
        self::EXECUTOR => [
            'page:new', 'page:write', 'page:read', 'page:list',
            'image:search', 'image:download',
            'domain:list', 'allias:list',
        ],
        self::DESIGNER => [
            'page:write',
            'skin:edit', 'skin:list',
            'block:edit', 'filler:edit',
            'scheme:save', 'scheme:list',
            'block:list', 'filler:list', 'page:list', 'page:read',
        ],
        self::BUNDLER => [
            'structure:check',
            'skin:list',
            'domain:list', 'allias:list', 'page:list',
        ],
        self::CORRECTOR => [
            'page:write', 'structure:repair', 'structure:check',
            'page:read', 'domain:list', 'allias:list', 'page:list',
        ],
        self::REPORTER => [
            'domain:list', 'allias:list', 'page:list', 'page:read',
            'scheme:list', 'block:list', 'filler:list',
            'structure:check',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function whitelist(string $role): array
    {
        if ($role === '*') {
            $all = [];
            foreach (self::MAP as $cmds) {
                $all = array_merge($all, $cmds);
            }
            return array_values(array_unique(array_merge($all, [
                'ai:start', 'done', 'engine_status',
                'net:status', 'image:search', 'image:download', 'fetch',
                'skin:remove',
                'domain:list', 'allias:list', 'page:list', 'page:read',
                'scheme:list', 'block:list', 'filler:list', 'skin:list',
            ])));
        }
        return self::MAP[$role] ?? [];
    }

    public static function isValid(string $role): bool
    {
        return $role === '*' || isset(self::MAP[$role]);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::MAP);
    }
}
