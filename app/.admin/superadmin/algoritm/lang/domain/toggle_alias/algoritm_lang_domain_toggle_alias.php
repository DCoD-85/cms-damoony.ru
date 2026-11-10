<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class algoritm_lang_domain_toggle_alias extends AdminPage
{
    protected function content(): Node|Response|null
    {
        if (!$this->context->request->isPost()) {
            return Response::json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
        }

        $domain = (string) $this->context->request->post('domain');
        $alias = (string) $this->context->request->post('alias');
        $active = $this->context->request->post('active') === 'true';

        if ($domain === '' || $alias === '') {
            return Response::json(['ok' => false, 'error' => 'Domain or Alias is empty'], 400);
        }

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::json(['ok' => false, 'error' => 'Paths service missing'], 500);
        }

        $siteFile = $paths->siteConfig();
        $site = Config::fromFile($siteFile)->all();

        if (!isset($site['alliases_available']) || !is_array($site['alliases_available'])) {
            $site['alliases_available'] = [];
        }
        if (!isset($site['alliases_available'][$domain]) || !is_array($site['alliases_available'][$domain])) {
            $site['alliases_available'][$domain] = [];
        }
        
        $site['alliases_available'][$domain][$alias] = $active;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        return Response::json(['ok' => true, 'domain' => $domain, 'alias' => $alias, 'active' => $active]);
    }
}
