<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;
use Damoony\Ai\Roles;

final class AiStartHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['ai:start'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        return AicliResult::ok([
            'state' => ['domains' => $this->readDomainsFromSiteConfig()],
            'allowed_domains' => $this->allowedDomains,
            'whitelist' => Roles::whitelist($role),
        ]);
    }

    /**
     * @return list<string>
     */
    private function readDomainsFromSiteConfig(): array
    {
        $siteFile = $this->root . '/config/site.config';
        if (!is_file($siteFile)) {
            return [];
        }

        $raw = file_get_contents($siteFile);
        if ($raw === false) {
            return [];
        }

        $site = json_decode($raw, true);
        if (!is_array($site) || !isset($site['domains_real']) || !is_array($site['domains_real'])) {
            return [];
        }

        $set = [];
        foreach ($site['domains_real'] as $real) {
            if (is_string($real) && $real !== '') {
                $set[$real] = true;
            }
        }

        $domains = array_keys($set);
        sort($domains);

        return $domains;
    }
}
