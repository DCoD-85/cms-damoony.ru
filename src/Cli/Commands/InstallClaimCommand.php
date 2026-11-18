<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Persona\PersonaRepository;

/**
 * install:claim — проверка установки с клиента: домен, статус, срок; фиксирует claim.
 */
final class InstallClaimCommand implements CommandInterface
{
    public function __construct(private readonly PersonaRepository $persona)
    {
    }

    public function name(): string
    {
        return 'install:claim';
    }

    public function description(): string
    {
        return 'Проверить установочный токен (--token=, --domain=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $token = (string) ($args['token'] ?? '');
        $domain = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));

        $installation = $token === ''
            ? []
            : $this->persona->installationByToken(PersonaTokens::hash($token));

        if ($installation === []) {
            $r->error('Токен установки не найден');

            return $r;
        }
        if (($installation['status'] ?? '') !== 'active') {
            $r->error('Установка не подтверждена администратором');

            return $r;
        }
        if ($domain === '' || $domain !== (string) $installation['domain']) {
            $r->error('Домен не совпадает с заявкой');

            return $r;
        }

        $expires = (string) ($installation['expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            $r->error('Срок действия установки истёк');

            return $r;
        }

        $this->persona->markClaimed((int) $installation['id']);

        $r->data = [
            'installation_id' => (int) $installation['id'],
            'account_id' => (int) $installation['account_id'],
            'domain' => $domain,
            'assembly' => (string) $installation['assembly'],
            'langs' => array_values(array_filter(explode(',', (string) $installation['langs']))),
        ];

        $context->step('Установка подтверждена: ' . $domain, true, 'install');

        return $r;
    }
}
