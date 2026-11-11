<?php

declare(strict_types=1);

namespace Damoony\Data\Db\DataProvider;

use Damoony\Core\Files\Paths;

final class ProviderResolver
{
    /**
     * @return class-string|null
     */
    public static function resolveClass(string $provider, string $domain, string $alias, Paths $paths): ?string
    {
        // 0. Кросс-алиасный доступ (например, "doc.articles")
        if (str_contains($provider, '.')) {
            [$targetAlias, $actualProvider] = explode('.', $provider, 2);
            $crossFile = $paths->appRoot() . '/' . $domain . '/' . $targetAlias . '/.dataprovider/' . ucfirst($actualProvider) . 'Provider.php';
            if (is_file($crossFile)) {
                require_once $crossFile;
                $namespace = str_replace(['.', '-'], '_', $domain) . '\\' . $targetAlias;
                $crossClass = $namespace . '\\' . ucfirst($actualProvider) . 'Provider';
                if (class_exists($crossClass, false)) {
                    return $crossClass;
                }
            }
            $provider = $actualProvider; // Ищем базовый в ядре, если кастомного нет
        }

        // 1. Провайдер уровня Алиаса (app/domain/alias/.dataprovider/)
        $customFile = $paths->appRoot() . '/' . $domain . '/' . $alias . '/.dataprovider/' . ucfirst($provider) . 'Provider.php';
        if (is_file($customFile)) {
            require_once $customFile;
            $namespace = str_replace(['.', '-'], '_', $domain) . '\\' . $alias;
            $customClass = $namespace . '\\' . ucfirst($provider) . 'Provider';
            if (class_exists($customClass, false)) {
                return $customClass;
            }
        }

        // 2. Провайдер уровня Домена (app/domain/.dataprovider/)
        $domainFile = $paths->appRoot() . '/' . $domain . '/.dataprovider/' . ucfirst($provider) . 'Provider.php';
        if (is_file($domainFile)) {
            require_once $domainFile;
            $domainNamespace = str_replace(['.', '-'], '_', $domain);
            $domainClass = $domainNamespace . '\\' . ucfirst($provider) . 'Provider';
            if (class_exists($domainClass, false)) {
                return $domainClass;
            }
        }

        // 2. Если кастомного нет — проверяем ЯДРО
        $map = [
            'user' => UserProvider::class,
            'news' => NewsProvider::class,
            'files' => FilesProvider::class,
            'audit' => AuditProvider::class,
            'form_submission' => FormSubmissionProvider::class,
        ];

        return $map[$provider] ?? null;
    }
}
