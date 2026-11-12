<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\EnsuresSessionPermission;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Region\BlockCompiler;

final class FillerWriteCommand implements CommandInterface
{
    use EnsuresSessionPermission;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'filler:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в filler';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $block = (string) ($args['block'] ?? '');
        $sessionId = trim((string) ($args['session'] ?? ''));

        $jsonB64 = (string) ($args['json_b64'] ?? '');
        $json = (string) ($args['json'] ?? '');

        if ($jsonB64 !== '') {
            $json = base64_decode($jsonB64, true) ?: '';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            $r->error('Некорректный алиас или имя блока');
            return $r;
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) ($site['domains_real'][$domain] ?? '');
        if ($realDomain === '') {
            $r->error('Домен не найден в site.config');
            return $r;
        }
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        try {
            $this->ensureSessionPermission($this->paths, $sessionId, $realDomain, 'filler:write');
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $baseDir = $this->resolveDomainBase($this->paths, $realDomain, $sessionId);

        // Приоритет: .blockslocal/{block}/{block}.php (new model), fallback — .blocksglobal legacy.
        $localTarget = $baseDir . '/.blockslocal/' . $block . '/' . $block . '.php';
        $legacyTarget = $baseDir . '/.blocksglobal/' . $block . '/' . $block . '_' . $allias . '.php';

        if (Fs::isFile($localTarget)) {
            $target = $localTarget;
            $className = 'blockslocal_' . $allias . '_' . $block;
        } elseif (Fs::isFile($legacyTarget)) {
            $target = $legacyTarget;
            $className = 'blocksglobal_' . $block . '_' . $allias;
        } else {
            $r->error('Filler не найден (сначала создайте его через filler:new)');
            return $r;
        }
        $compiler = new BlockCompiler();
        $phpCode = $compiler->compile($namespace, $className, $block, array_values($blocks));

        Fs::writeAtomic($target, $phpCode);

        $r->file(Fs::rel($target, $this->paths->root()));
        $context->step('Контент филлера обновлен', true, $className);

        return $r;
    }
}
