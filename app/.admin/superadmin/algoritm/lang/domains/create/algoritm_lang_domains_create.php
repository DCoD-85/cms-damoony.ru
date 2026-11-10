<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Builder;
use Damoony\UI\Components\Card;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Label;
use Damoony\Core\Files\Paths;

final class algoritm_lang_domains_create extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $paths = $this->context->service('paths');
        $flash = $this->context->service('flash');

        if ($this->context->request->isPost()) {
            $domain = trim((string) $this->context->request->post('domain', ''));
            
            if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
                if ($flash instanceof \Damoony\Core\Flash) {
                    $flash->error($t->t('admin.domain.create.error_format'));
                }
            } else {
                $phpBinary = PHP_BINARY;
                $cliPath = $paths instanceof Paths ? $paths->root() . '/bin/cli.php' : 'bin/cli.php';
                $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($cliPath) . ' domain:new --domain=' . escapeshellarg($domain) . ' 2>&1';
                
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0) {
                    if ($flash instanceof \Damoony\Core\Flash) {
                        $flash->success($t->t('admin.domain.create.success', ['domain' => $domain]));
                    }
                    return Response::redirect("/admin/{$lang}/domains");
                } else {
                    if ($flash instanceof \Damoony\Core\Flash) {
                        $flash->error('Ошибка CLI: ' . implode(' | ', $output));
                    }
                }
            }
        }

        $content = new Card($t->t('admin.domain.create.new'));

        $form = new Form();
        $form->action("/admin/{$lang}/domains/create")->method('POST');
        
        $form->add(new Label($t->t('admin.domain.create.fqdn')));
        $form->add(new Input('text', 'domain', ''));
        $form->add(new Button($t->t('admin.domain.create.submit')));

        $content->add($form);

        $backBtn = new \Damoony\UI\Components\Link("/admin/{$lang}/domains", $t->t('admin.domain.create.back'));
        $backBtn->attrs()->classAdd('ui-btn')->classAdd('ui-btn-outline');
        $content->add($backBtn);

        $this->setContent('center', $content);

        return $content;
    }
}
