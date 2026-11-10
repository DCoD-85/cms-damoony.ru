<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class algoritm_lang_domains_toggle extends AdminPage
{
    protected function content(): Node|Response|null
    {
        // Эндпоинт принимает только POST
        if (!$this->context->request->isPost()) {
            return Response::json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
        }

        $domain = (string) $this->context->request->post('domain');
        $active = $this->context->request->post('active') === 'true';

        if ($domain === '') {
            return Response::json(['ok' => false, 'error' => 'Domain is empty'], 400);
        }

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::json(['ok' => false, 'error' => 'Paths service missing'], 500);
        }

        $siteFile = $paths->siteConfig();
        if (!is_file($siteFile)) {
            return Response::json(['ok' => false, 'error' => 'site.config not found'], 500);
        }

        $site = Config::fromFile($siteFile)->all();

        // Обновляем статус
        if (!isset($site['domains_available']) || !is_array($site['domains_available'])) {
            $site['domains_available'] = [];
        }
        $site['domains_available'][$domain] = $active;

        // Сохраняем конфиг
        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        return Response::json(['ok' => true, 'domain' => $domain, 'active' => $active]);
    }
}
