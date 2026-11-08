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
        $map = [
            'user' => UserProvider::class,
            'news' => NewsProvider::class,
            'files' => FilesProvider::class,
            'audit' => AuditProvider::class,
            'form_submission' => FormSubmissionProvider::class,
        ];
        $coreClass = $map[$provider] ?? null;
        if ($coreClass === null) {
            return null;
        }

        // Кастомные провайдеры: app/{domain}/{alias}/provider/{Name}Provider.php
        $customFile = $paths->appRoot() . '/' . $domain . '/' . $alias . '/provider/' . ucfirst($provider) . 'Provider.php';

        if (is_file($customFile)) {
            require_once $customFile;
            $namespace = str_replace(['.', '-'], '_', $domain) . '\\' . $alias . '\\provider';
            $customClass = $namespace . '\\' . ucfirst($provider) . 'Provider';
            if (class_exists($customClass)) {
                return $customClass;
            }
        }

        return $coreClass;
    }
}
