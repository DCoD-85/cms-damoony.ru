<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Ai\Roles;
use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;
use Damoony\Core\Settings\SiteConfig;

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

        try {
            $site = SiteConfig::fromFile($siteFile);
        } catch (\Throwable) {
            return [];
        }

        $domains = $site->projectKeys();
        sort($domains);

        return $domains;
    }
}
