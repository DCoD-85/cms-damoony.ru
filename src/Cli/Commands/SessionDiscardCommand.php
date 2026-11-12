<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;

final class SessionDiscardCommand extends AbstractSessionCommand
{
    public function name(): string
    {
        return 'session:discard';
    }

    public function description(): string
    {
        return 'Discard AI edit session (move to trash)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $tab = $args['tab'] ?? '';

        try {
            $this->validateDomain($domain);
            $this->validateTab($tab);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        $realDomain = (string) ($site['domains_real'][$domain] ?? '');
        if ($realDomain === '') {
            $r->error('Домен не найден в site.config');
            return $r;
        }

        $sessionDir = $this->paths->sessionDir($tab, $realDomain);
        if (!is_dir($sessionDir)) {
            $r->error('Session not found');
            return $r;
        }

        $trashDir = $this->paths->appRoot() . '/' . $this->trashName($tab, $realDomain);
        if (!@rename($sessionDir, $trashDir)) {
            $r->error('Cannot move session to trash');
            return $r;
        }

        $r->data = ['discarded' => true, 'tab_id' => $tab, 'trash' => $trashDir];
        $context->step("Discarded session {$tab}", true);

        return $r;
    }
}
