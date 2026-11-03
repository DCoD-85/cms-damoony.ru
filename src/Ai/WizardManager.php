<?php

declare(strict_types=1);

namespace Damoony\Ai;

use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class WizardManager
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';
    private const DOMAIN_RE = '/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/';

    /** @var callable(string): array{ok: bool, output: string, error: string, hint: string} */
    private $cliRunner;

    /** @var callable(string): array<string, mixed> */
    private $cliJsonRunner;

    public function __construct(
        private readonly Paths $paths,
        callable $cliRunner,
        callable $cliJsonRunner,
    ) {
        $this->cliRunner = $cliRunner;
        $this->cliJsonRunner = $cliJsonRunner;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function handle(WizardSession $session, string $action, array $args): Result
    {
        $r = new Result();

        try {
            match ($action) {
                'domain' => $this->setDomain($session, (string) ($args['domain'] ?? ''), $r),
                'allias' => $this->setAllias($session, (string) ($args['allias'] ?? ''), $r),
                'plan' => $this->setPlan($session, $args['pages'] ?? [], $r),
                'content' => $this->setContent($session, (string) ($args['page'] ?? ''), $args['blocks'] ?? [], $r),
                'skin' => $this->setSkin($session, (string) ($args['file'] ?? ''), (string) ($args['content'] ?? ''), $r),
                'finish' => $this->finish($session, $r),
                'commit' => $this->commit($session, $r),
                default => $r->error("Unknown wizard action: {$action}"),
            };
        } catch (\Throwable $e) {
            $r->error($e->getMessage());
        }

        if ($r->ok) {
            $session->save($this->paths);
        }

        return $r;
    }

    private function setDomain(WizardSession $session, string $domain, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_INIT) {
            $r->error('Invalid stage for domain. Expected: init, current: ' . $session->stage());
            return;
        }

        if (!preg_match(self::DOMAIN_RE, $domain) || str_contains($domain, '..')) {
            $r->error('Invalid domain format. Must be FQDN (e.g., example.ru)');
            return;
        }

        $session->set('domain', $domain);
        $session->advanceTo(WizardSession::STAGE_DOMAIN);
    }

    private function setAllias(WizardSession $session, string $allias, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_DOMAIN) {
            $r->error('Invalid stage for allias. Expected: domain, current: ' . $session->stage());
            return;
        }

        if (!preg_match(self::NAME_RE, $allias)) {
            $r->error('Invalid allias name. Must be lowercase alphanumeric with underscores.');
            return;
        }

        $session->set('allias', $allias);
        $session->advanceTo(WizardSession::STAGE_ALLIAS);
    }

    private function setPlan(WizardSession $session, mixed $pages, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_ALLIAS) {
            $r->error('Invalid stage for plan. Expected: allias, current: ' . $session->stage());
            return;
        }

        if (!is_array($pages)) {
            $r->error('Plan must be an array of pages.');
            return;
        }

        $names = [];
        $plan = [];
        $hasRoot = false;

        foreach ($pages as $page) {
            if (!is_array($page)) {
                $r->error('Each page must be an object.');
                return;
            }

            $name = (string) ($page['name'] ?? '');
            $parent = $page['parent'] ?? null;
            $title = (string) ($page['title'] ?? '');

            if ($name === '__root__') {
                if ($hasRoot) {
                    $r->error('Duplicate __root__ page.');
                    return;
                }
                $hasRoot = true;
                if ($parent !== null && $parent !== '') {
                    $r->error('__root__ cannot have a parent.');
                    return;
                }
            } else {
                if (!preg_match(self::NAME_RE, $name)) {
                    $r->error("Invalid page name '{$name}'. Must be lowercase alphanumeric with underscores.");
                    return;
                }
                if (isset($names[$name])) {
                    $r->error("Duplicate page name '{$name}'.");
                    return;
                }
                $names[$name] = true;
            }

            if ($parent !== null && $parent !== '' && $parent !== '__root__') {
                if (!preg_match(self::NAME_RE, (string) $parent)) {
                    $r->error("Invalid parent name '{$parent}' for page '{$name}'.");
                    return;
                }
            }

            $plan[] = ['name' => $name, 'title' => $title, 'parent' => $parent === '' ? null : $parent];
        }

        if (!$hasRoot) {
            $r->error('Plan must include exactly one __root__ page.');
            return;
        }

        foreach ($plan as $p) {
            if ($p['name'] === '__root__') {
                continue;
            }
            $parent = $p['parent'];
            if ($parent !== null && $parent !== '__root__' && !isset($names[$parent])) {
                $r->error("Parent '{$parent}' for page '{$p['name']}' does not exist in the plan.");
                return;
            }
        }

        $session->set('plan', $plan);
        $session->advanceTo(WizardSession::STAGE_PLAN);
    }

    private function setContent(WizardSession $session, string $page, mixed $blocks, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_PLAN && $session->stage() !== WizardSession::STAGE_CONTENT) {
            $r->error('Invalid stage for content. Expected: plan or content, current: ' . $session->stage());
            return;
        }

        $plan = $session->get('plan', []);
        $found = false;
        foreach ($plan as $p) {
            if ($p['name'] === $page) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $r->error("Page '{$page}' not found in the plan.");
            return;
        }

        if (!is_array($blocks)) {
            $r->error('Blocks must be an array.');
            return;
        }

        $content = $session->get('content', []);
        $content[$page] = $blocks;
        $session->set('content', $content);
        $session->advanceTo(WizardSession::STAGE_CONTENT);
    }

    private function setSkin(WizardSession $session, string $file, string $content, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_CONTENT && $session->stage() !== WizardSession::STAGE_SKIN) {
            $r->error('Invalid stage for skin. Expected: content or skin, current: ' . $session->stage());
            return;
        }

        if (!preg_match('/^(css\/[a-z0-9_]+\.css|js\/main\.js)$/', $file)) {
            $r->error('Invalid skin file. Allowed: css/*.css, js/main.js');
            return;
        }

        $skins = $session->get('skin', []);
        $skins[$file] = $content;
        $session->set('skin', $skins);
        $session->advanceTo(WizardSession::STAGE_SKIN);
    }

    private function finish(WizardSession $session, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_CONTENT && $session->stage() !== WizardSession::STAGE_SKIN) {
            $r->error('Invalid stage for finish. Expected: content or skin, current: ' . $session->stage());
            return;
        }

        $plan = $session->get('plan', []);
        $content = $session->get('content', []);

        foreach ($plan as $p) {
            if (!isset($content[$p['name']])) {
                $r->error("Missing content for page '{$p['name']}'. Use wizard:content first.");
                return;
            }
        }

        $session->advanceTo(WizardSession::STAGE_READY);
    }

    private function commit(WizardSession $session, Result $r): void
    {
        if ($session->stage() !== WizardSession::STAGE_READY) {
            $r->error('Invalid stage for commit. Expected: ready, current: ' . $session->stage());
            return;
        }

        $domain = (string) $session->get('domain');
        $allias = (string) $session->get('allias');
        $plan = (array) $session->get('plan', []);
        $content = (array) $session->get('content', []);
        $skins = (array) $session->get('skin', []);

        $run = $this->cliRunner;
        $runJson = $this->cliJsonRunner;
        $undo = [];

        try {
            // 1. Domain
            $domainExists = false;
            $resDomains = $runJson('domain:list');
            if ($resDomains['ok'] ?? false) {
                $domainsList = $resDomains['data']['rows'] ?? $resDomains['data']['domains'] ?? [];
                foreach ($domainsList as $d) {
                    $name = is_array($d) ? ($d['domain'] ?? $d['name'] ?? '') : $d;
                    if ($name === $domain) {
                        $domainExists = true;
                        break;
                    }
                }
            }

            if (!$domainExists) {
                $res = $run('domain:new --domain=' . escapeshellarg($domain));
                if (!$res['ok']) {
                    throw new \RuntimeException('domain:new failed: ' . $res['output']);
                }
                $undo[] = 'domain:remove --domain=' . escapeshellarg($domain) . ' --force';
            }

            // 2. Allias
            $res = $run('allias:remove --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($allias) . ' --force');
            if (!$res['ok']) {
                throw new \RuntimeException('allias:remove failed: ' . $res['output']);
            }

            $res = $run('allias:new --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($allias));
            if (!$res['ok']) {
                throw new \RuntimeException('allias:new failed: ' . $res['output']);
            }
            $undo[] = 'allias:remove --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($allias) . ' --force';

            // 3. Skin
            $skinDir = $this->paths->media($domain, $allias) . '/skin/default';
            if (!Fs::isDir($skinDir)) {
                $res = $run('skin:new --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --name=default');
                if (!$res['ok']) {
                    throw new \RuntimeException('skin:new failed: ' . $res['output']);
                }
            }

            // 4. Blocks & Filler
            foreach (['top', 'left', 'right', 'bottom', 'policy'] as $b) {
                $blockDir = $this->paths->domain($domain) . '/.alliasglobal/' . $b;
                if (!Fs::isDir($blockDir)) {
                    $res = $run('block:new --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($b) . ' --type=visual');
                    if (!$res['ok']) {
                        throw new \RuntimeException("block:new {$b} failed: " . $res['output']);
                    }
                }
            }

            $fillerFile = $this->paths->domain($domain) . '/.alliasglobal/top/top_' . $allias . '.php';
            if (!is_file($fillerFile)) {
                $res = $run('filler:new --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --block=top');
                if (!$res['ok']) {
                    throw new \RuntimeException('filler:new failed: ' . $res['output']);
                }
            }

            // ==========================================
            // СОЗДАЕМ КАРТУ ПУТЕЙ ДЛЯ ВСЕХ СТРАНИЦ (Меню + Файлы)
            // ==========================================
            $pathMap = ['__root__' => ''];
            foreach ($plan as $p) {
                if ($p['name'] === '__root__') continue;
                $parent = $p['parent'] ?? '';
                $parentPath = ($parent === '__root__' || $parent === null || $parent === '') ? '' : ($pathMap[$parent] ?? $parent);
                $pathMap[$p['name']] = $parentPath === '' ? $p['name'] : $parentPath . '/' . $p['name'];
            }

            // 5. Меню
            $menuItems = [['title' => 'Главная', 'url' => '/']];
            foreach ($plan as $p) {
                if ($p['name'] === '__root__') continue;
                $url = '/' . $pathMap[$p['name']]; // Берем полный путь из карты
                $menuItems[] = ['title' => $p['title'], 'url' => $url];
            }

            $navBlocks = [['type' => 'nav', 'items' => $menuItems, 'class' => 'main-navigation']];
            $encodedNav = base64_encode((string) json_encode($navBlocks, JSON_UNESCAPED_UNICODE));
            $res = $run('filler:write --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --block=top --json_b64=' . escapeshellarg($encodedNav));
            if (!$res['ok']) {
                throw new \RuntimeException('filler:write top failed: ' . $res['output']);
            }

            // 6. Схема
            $schemeDir = $this->paths->domain($domain) . '/.alliasscheme';
            $editorFile = $schemeDir . '/' . $allias . '.editor.json';
            if (!Fs::isFile($editorFile)) {
                $res = $run('scheme:new --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($allias));
                if (!$res['ok']) {
                    throw new \RuntimeException("scheme:new failed: " . $res['output']);
                }
            }
            
            $schemeJson = [
                'children' => array_map(static fn (string $s): array => ['type' => 'slot', 'name' => $s], ['top', 'left', 'center', 'right', 'bottom', 'policy']),
                'meta' => [
                    'css' => ['/media/shared/css/main.css', "/media/{$domain}/{$allias}/skin/default/css/main.css"],
                    'js' => ['/media/shared/scripts/jquery.min.js'],
                ],
            ];
            $encodedScheme = base64_encode((string) json_encode($schemeJson, JSON_UNESCAPED_UNICODE));
            $res = $run('scheme:save --domain=' . escapeshellarg($domain) . ' --name=' . escapeshellarg($allias) . ' --scheme_json_b64=' . escapeshellarg($encodedScheme));
            if (!$res['ok']) {
                throw new \RuntimeException('scheme:save failed: ' . $res['output']);
            }

            // 7. Страницы (Создание папок)
            usort($plan, static function (array $a, array $b): int {
                $depthA = $a['name'] === '__root__' ? 0 : ($a['parent'] ? 2 : 1);
                $depthB = $b['name'] === '__root__' ? 0 : ($b['parent'] ? 2 : 1);
                return $depthA <=> $depthB;
            });

            foreach ($plan as $p) {
                $name = $p['name'];
                if ($name === '__root__') continue;

                $fullRelPath = $pathMap[$name]; 
                $pageDir = $this->paths->algoritm($domain, $allias) . '/' . $fullRelPath;
                
                if (!Fs::isDir($pageDir)) {
                    $args = 'page:new --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --name=' . escapeshellarg($name);
                    
                    $parentPath = $p['parent'] && $p['parent'] !== '__root__' ? ($pathMap[$p['parent']] ?? $p['parent']) : '';
                    if ($parentPath !== '') {
                        $args .= ' --parent=' . escapeshellarg($parentPath);
                    }
                    
                    $res = $run($args);
                    if (!$res['ok']) {
                        throw new \RuntimeException("page:new {$name} failed: " . $res['output']);
                    }
                }
            }

            // 8. Контент
            $tmpDir = $this->paths->root() . '/var/tmp';
            if (!Fs::isDir($tmpDir)) {
                Fs::mkdir($tmpDir);
            }

            foreach ($plan as $p) {
                $name = $p['name'];
                $cliName = ($name === '__root__') ? '__root__' : $name;
                $blocks = $content[$name] ?? [];

                $tmpFile = $tmpDir . '/wizard_blocks_' . bin2hex(random_bytes(4)) . '.json';
                Fs::writeAtomic($tmpFile, (string) json_encode($blocks, JSON_UNESCAPED_UNICODE));

                try {
                    $writeArgs = 'page:write --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --name=' . escapeshellarg($cliName) . ' --json_file=' . escapeshellarg($tmpFile) . ' --with_schema=1';
                    
                    $parentPath = $p['parent'] && $p['parent'] !== '__root__' ? ($pathMap[$p['parent']] ?? $p['parent']) : '';
                    if ($parentPath !== '') {
                        $writeArgs .= ' --parent=' . escapeshellarg($parentPath);
                    }
                    
                    $res = $run($writeArgs);
                    if (!$res['ok']) {
                        throw new \RuntimeException("page:write {$name} failed: " . $res['output']);
                    }
                } finally {
                    @unlink($tmpFile);
                }
            }

            // 9. Скины
            foreach ($skins as $file => $cssContent) {
                $encodedCss = base64_encode((string) $cssContent);
                $res = $run('skin:edit --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --name=default --file=' . escapeshellarg($file) . ' --content_b64=' . escapeshellarg($encodedCss));
                if (!$res['ok']) {
                    throw new \RuntimeException("skin:edit {$file} failed: " . $res['output']);
                }
            }

            // Empty CSS placeholders
            foreach (['top', 'left', 'right', 'bottom', 'policy'] as $b) {
                $emptyCss = base64_encode("/* {$b}.css */\n");
                $run('skin:edit --domain=' . escapeshellarg($domain) . ' --allias=' . escapeshellarg($allias) . ' --name=default --file=' . escapeshellarg("css/{$b}.css") . ' --content_b64=' . escapeshellarg($emptyCss));
            }

            $session->advanceTo(WizardSession::STAGE_COMMITTED);
            $r->step('Wizard committed successfully', true, $domain);
        } catch (\Throwable $e) {
            $r->error('Commit failed: ' . $e->getMessage());
            foreach (array_reverse($undo) as $undoCmd) {
                $run($undoCmd);
            }
            $r->step('Rollback executed', true);
        }
    }
}
