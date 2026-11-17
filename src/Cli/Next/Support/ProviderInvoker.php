<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Config;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Data\Db\DataProvider\ProviderResolver;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Общий слой вызова DataProvider из CLI (db:call / db:mutate).
 *
 * Резолв класса провайдера, разбор --params/--params_b64 и приведение
 * аргументов к сигнатуре метода через Reflection. Ошибки возвращаются текстом:
 * команда сама решает, в какой части Result их положить.
 */
final class ProviderInvoker
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function resolveClass(string $provider, string $folder, string $alias): ?string
    {
        return ProviderResolver::resolveClass($provider, $folder, $alias, $this->paths);
    }

    /**
     * Config для конструктора DataProvider.
     *
     * Фаза 2: AbstractProvider пока принимает Damoony\Core\Config и читает
     * `domains_aliases_storage` для префикса. Здесь этот контракт собирается из
     * новых полей проекта; после перевода движка на SiteConfig метод уходит.
     */
    public function providerConfig(ProjectSettings $project, string $alias): Config
    {
        return Config::fromArray([
            'domains_aliases_storage' => [
                $project->folder => [
                    $alias => [
                        'prefix' => $project->storagePrefix($alias),
                        'db' => $project->storageDb($alias),
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null null = некорректный JSON
     */
    public function params(array $args): ?array
    {
        $b64 = (string) ($args['params_b64'] ?? '');
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                return null;
            }
            $parsed = json_decode($decoded, true);

            return is_array($parsed) ? $parsed : null;
        }

        $parsed = json_decode((string) ($args['params'] ?? '{}'), true);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Приводит именованные параметры к позиционным по сигнатуре метода.
     *
     * @param array<string, mixed> $params
     *
     * @return array{args?: list<mixed>, error?: string}
     */
    public function bind(ReflectionMethod $method, array $params): array
    {
        $refParams = $method->getParameters();

        if (count($refParams) === 1
            && $refParams[0]->getType() instanceof ReflectionNamedType
            && $refParams[0]->getType()->getName() === 'array') {
            return ['args' => [$params]];
        }

        $names = array_map(
            static fn (ReflectionParameter $p): string => $p->getName(),
            $refParams,
        );

        $unknown = array_diff(array_keys($params), $names);
        if ($unknown !== []) {
            return ['error' => 'Неизвестные параметры: ' . implode(', ', $unknown)];
        }

        $ordered = [];
        foreach ($refParams as $refParam) {
            $name = $refParam->getName();

            if (!array_key_exists($name, $params)) {
                if ($refParam->isOptional()) {
                    $ordered[] = $refParam->getDefaultValue();

                    continue;
                }

                return ['error' => "Не передан обязательный параметр '{$name}'"];
            }

            $typeName = $refParam->getType() instanceof ReflectionNamedType
                ? $refParam->getType()->getName()
                : 'mixed';

            $cast = $this->cast($params[$name], $typeName);
            if ($cast === null) {
                return ['error' => "Параметр '{$name}' ожидает {$typeName}, получено " . gettype($params[$name])];
            }

            $ordered[] = $cast;
        }

        return ['args' => $ordered];
    }

    /**
     * @return string|int|bool|array<mixed>|null null = несовместимый тип
     */
    private function cast(mixed $value, string $typeName): string|int|bool|array|null
    {
        return match ($typeName) {
            'string' => is_string($value)
                ? $value
                : (is_int($value) || is_float($value) ? (string) $value : null),
            'int' => is_int($value) ? $value : (is_numeric($value) ? (int) $value : null),
            'bool' => is_bool($value) ? $value : null,
            'array' => is_array($value) ? $value : null,
            default => null,
        };
    }
}
