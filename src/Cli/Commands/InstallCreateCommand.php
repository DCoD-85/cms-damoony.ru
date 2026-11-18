<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Persona\PersonaRepository;

/**
 * install:create — заявка/создание установки с одноразовым токеном.
 */
final class InstallCreateCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly PersonaRepository $persona,
    ) {
    }

    public function name(): string
    {
        return 'install:create';
    }

    public function description(): string
    {
        return 'Создать установку (--account=email, --domain=, --assembly=, --langs=, --approve)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $email = strtolower(trim((string) ($args['account'] ?? '')));
        $domain = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $assembly = trim((string) ($args['assembly'] ?? 'minimal'));
        $langs = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($args['langs'] ?? 'ru,en')),
        )));
        $approve = isset($args['approve']) && (string) $args['approve'] !== '0';

        if (!SiteConfig::isValidHost($domain)) {
            $r->error('Некорректный домен');

            return $r;
        }
        if (!is_dir($this->paths->root() . '/stubs/assemblies/' . $assembly)) {
            $r->error('Сборка не найдена: ' . $assembly);

            return $r;
        }
        if ($langs === []) {
            $r->error('Укажите языки: --langs=ru,en');

            return $r;
        }

        $account = $this->persona->accountByEmail($email);
        if ($account === []) {
            $r->error('Аккаунт не найден: ' . $email);

            return $r;
        }

        $token = PersonaTokens::generate();
        $status = $approve ? 'active' : 'pending';

        $installationId = $this->persona->createInstallation(
            (int) $account['id'],
            $domain,
            $assembly,
            $langs,
            PersonaTokens::hash($token),
            $status,
        );

        $r->data = [
            'installation_id' => $installationId,
            'domain' => $domain,
            'assembly' => $assembly,
            'langs' => $langs,
            'status' => $status,
            'token' => $token,
            'install_url' => 'https://persona.damoony.ru/install/' . $token,
        ];

        $context->step(
            'Установка #' . $installationId . ' для ' . $domain . ' (' . $status . ')',
            true,
            'install',
        );

        return $r;
    }
}
