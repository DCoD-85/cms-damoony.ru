<?php

declare(strict_types=1);

namespace {{namespace}}\developer;

use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Data\Persona\PersonaAuth;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;

final class algoritm_lang extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $domain = $this->context->domain->real;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $personaBase = $scheme . '://persona.' . $domain;

        $auth = $this->context->service('persona_auth');
        $repo = $this->context->service('persona');
        if (!$auth instanceof PersonaAuth || !$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect($personaBase . '/' . $lang . '/');
        }

        $accountId = (int) $user['id'];
        $member = $repo->member($accountId, 'developer');
        $memberStatus = (string) ($member['status'] ?? '');

        $issuedUrl = null;

        if ($this->isPost() && $memberStatus === 'active') {
            $action = (string) $this->context->request->post('action');

            if ($action === 'download') {
                $installationId = (int) $this->context->request->post('installation_id');
                $installation = $repo->installationById($installationId);
                $paths = $this->context->service('paths');

                if ($installation !== []
                    && (int) $installation['account_id'] === $accountId
                    && (string) $installation['status'] === 'active'
                    && $paths instanceof \Damoony\Core\Files\Paths) {
                    $builder = new \Damoony\Data\Persona\InstallationBuilder($paths, $repo);
                    $build = $builder->build($installation);
                    $file = $builder->filePath((string) $build['file']);

                    if (is_file($file)) {
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                        header('Content-Length: ' . (string) filesize($file));
                        readfile($file);
                        exit;
                    }
                }

                return Response::notFound();
            }

            if ($action === 'create') {
                $target = \Damoony\Core\Settings\SiteConfig::normalizeHost((string) $this->context->request->post('domain'));
                $assembly = (string) $this->context->request->post('assembly');
                $paths = $this->context->service('paths');
                $aliases = [];
                $langs = [];

                if ($paths instanceof \Damoony\Core\Files\Paths) {
                    try {
                        $manifest = \Damoony\Core\Assembly\AssemblyManifest::fromDirectory($paths, $assembly);
                        $aliases = array_values(array_intersect(
                            (array) $this->context->request->post('aliases', []),
                            $manifest->aliases(),
                        ));
                        if ($aliases === []) {
                            $aliases = [$manifest->aliases()[0]];
                        }

                        $langs = array_values(array_intersect(
                            (array) $this->context->request->post('langs', []),
                            $manifest->projectLangs(),
                        ));
                        if (!in_array($manifest->baseLang(), $langs, true)) {
                            $langs[] = $manifest->baseLang();
                        }
                    } catch (\Throwable) {
                        $aliases = [];
                    }
                }

                if (\Damoony\Core\Settings\SiteConfig::isValidHost($target) && $aliases !== []) {
                    $token = PersonaTokens::generate();
                    $repo->createInstallation($accountId, $target, $assembly, $langs, PersonaTokens::hash($token), 'pending', $aliases);

                    $paths = $this->context->service('paths');
                    if ($paths instanceof \Damoony\Core\Files\Paths) {
                        (new \Damoony\Data\Persona\AdminNotifier($paths))->notify(
                            'Заявка на установку: ' . $target . ' (' . $assembly . ') от ' . (string) $user['email'],
                            'info',
                            'developer',
                        );
                    }
                }

                return Response::redirect('/' . $lang . '/');
            }

            if ($action === 'reissue') {
                $installationId = (int) $this->context->request->post('installation_id');
                $installation = $repo->installationById($installationId);

                if ($installation !== [] && (int) $installation['account_id'] === $accountId) {
                    $token = PersonaTokens::generate();
                    $repo->setInstallationToken($installationId, PersonaTokens::hash($token));
                    $issuedUrl = $scheme . '://' . $domain . '/install/' . $token;
                }
            }
        }

        $wrap = Builder::div('developer-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('developer.title')));
        $wrap->append(Builder::p()->content($this->context->lang('developer.account', ['email' => (string) $user['email']])));

        if ($memberStatus !== 'active') {
            $wrap->append(Builder::p('developer-status')->content(
                $memberStatus === 'pending'
                    ? $this->context->lang('developer.pending')
                    : $this->context->lang('developer.no_access'),
            ));
            $wrap->append(Builder::p()->append(
                Builder::a($personaBase . '/' . $lang . '/profile/')->content($this->context->lang('developer.open_persona')),
            ));

            return $wrap;
        }

        if ($issuedUrl !== null) {
            $wrap->append(Builder::p('developer-success')->content('Install: ' . $issuedUrl));
        }

        $installations = $repo->installationsFor($accountId);
        $wrap->append(Builder::h(2)->content($this->context->lang('developer.installations')));

        if ($installations === []) {
            $wrap->append(Builder::p()->content($this->context->lang('developer.no_installations')));
        } else {
            $list = Builder::ul('developer-installations');
            foreach ($installations as $installation) {
                $item = Builder::li();
                $item->append(Builder::span()->content(
                    (string) $installation['domain']
                    . ' — ' . (string) $installation['assembly']
                    . ' — ' . (string) $installation['status']
                    . ' — ' . implode(',', array_filter(explode(',', (string) ($installation['aliases'] ?? ''))))
                    . ' — ' . (string) $installation['langs'],
                ));

                $paths = $this->context->service('paths');
                $build = $repo->activeBuild((int) $installation['id']);
                $archive = $this->context->lang('developer.archive_on_demand');
                if ($build !== [] && $paths instanceof \Damoony\Core\Files\Paths) {
                    $file = $paths->root() . '/var/builds/' . basename((string) $build['file']);
                    $expires = (string) ($build['expires_at'] ?? '');
                    if (is_file($file) && $expires !== '' && strtotime($expires) > time()) {
                        $archive = $this->context->lang('developer.archive_until', ['date' => $expires]);
                    }
                }
                $item->append(Builder::span('developer-archive')->content($archive));

                $download = new Form('developer-download-form');
                $download->method('POST');
                $csrf = $this->context->service('csrf');
                if ($csrf instanceof Csrf) {
                    $download->csrf($csrf);
                }
                $download->add(new Input('hidden', 'action', 'download'));
                $download->add(new Input('hidden', 'installation_id', (string) $installation['id']));
                $download->add(new Button($this->context->lang('developer.download_archive')));
                $item->append($download);

                $form = new Form('developer-reissue-form');
                $form->method('POST');
                $csrf = $this->context->service('csrf');
                if ($csrf instanceof Csrf) {
                    $form->csrf($csrf);
                }
                $form->add(new Input('hidden', 'action', 'reissue'));
                $form->add(new Input('hidden', 'installation_id', (string) $installation['id']));
                $form->add(new Button($this->context->lang('developer.reissue')));
                $item->append($form);
                $list->append($item);
            }
            $wrap->append($list);
        }

        $wrap->append(Builder::h(2)->content($this->context->lang('developer.new_installation')));

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) {
            $form->csrf($csrf);
        }
        $form->add(new Input('hidden', 'action', 'create'));
        $form->add(new Input('text', 'domain'));
        $select = new Select('assembly');
        $select->add(new Option('minimal', 'minimal'));
        $select->add(new Option('showcase', 'showcase'));
        $form->add($select);

        foreach (['www', 'persona', 'developer', 'news', 'api', 'docs'] as $alias) {
            $form->add(new Input('checkbox', 'aliases[]', $alias));
        }
        foreach (['ru', 'en'] as $langCode) {
            $form->add(new Input('checkbox', 'langs[]', $langCode));
        }
        $form->add(new Button($this->context->lang('developer.submit')));
        $wrap->append($form);

        return $wrap;    }
}
