<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Text;

final class AliasSchemeController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Paths $paths,
        private readonly Config $siteConfig,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');
        if ($domain === '' || $allias === '') {
            return $this->redirect('/admin/?p=domain');
        }

        $localSchemeFile = $this->paths->domain($domain) . '/.alliasscheme/' . $allias . '.data.php';
        $hasLocal = is_file($localSchemeFile);

        $globalSchemeName = (string) $this->siteConfig->getByKey(['domains_scheme', $domain], 'www');

        $card = Builder::div('admin-card');

        $breadcrumbs = Builder::div('breadcrumbs');
        $breadcrumbs->append(Builder::a('/admin/?p=domain')->content(View::t('domain.bread_domains')));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=domain:edit&domain=' . urlencode($domain))->content($domain));
        $breadcrumbs->append(Text::raw(' / '));
        $breadcrumbs->append(Builder::a('/admin/?p=allias&domain=' . urlencode($domain))->content(View::t('tab.alliases')));
        $card->append($breadcrumbs);

        $card->append(Builder::h(1)->content(View::t('scheme.title') . ': ' . View::esc($allias)));
        $card->append(View::alliasTabs($domain, $allias, 'schemes'));

        // Информация о текущей схеме
        if ($hasLocal) {
            $card->append(Message::success(View::t('scheme.local_active') . " ({$allias}.data.php)")->build());

            // Кнопки управления
            $actions = Builder::div('page-actions');

            $editUrl = '/admin/?p=editor&domain=' . urlencode($domain) . '&file=' . urlencode('.alliasscheme/' . $allias . '.data.php');
            $actions->append(Builder::a($editUrl, 'btn btn-primary')->content(View::t('page.edit')));

            $delForm = Form::create('/admin/?p=allias_scheme:delete&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
                ->csrf($this->csrf->field())
                ->submit(View::t('scheme.delete_local'));
            $delBtn = $delForm->build();
            $actions->append($delBtn);

            $card->append($actions);
        } else {
            $card->append(Message::info(View::t('scheme.global_active') . " ({$globalSchemeName}.data.php)")->build());

            // Проверяем, существует ли глобальная схема, чтобы её скопировать
            $globalFile = $this->paths->domain($domain) . '/.alliasscheme/' . $globalSchemeName . '.data.php';

            if (is_file($globalFile)) {
                $form = Form::create('/admin/?p=allias_scheme:create&domain=' . urlencode($domain) . '&allias=' . urlencode($allias), 'POST')
                    ->csrf($this->csrf->field())
                    ->submit(View::t('scheme.create_local'));
                $card->append($form->build());
            } else {
                $card->append(Message::error(View::t('scheme.global_not_found'))->build());
            }
        }

        return Response::html($this->view->render($card, ['title' => View::t('scheme.title')]));
    }

    public function create(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');

        $globalSchemeName = (string) $this->siteConfig->getByKey(['domains_scheme', $domain], 'www');
        $globalFile = $this->paths->domain($domain) . '/.alliasscheme/' . $globalSchemeName . '.data.php';
        $localFile = $this->paths->domain($domain) . '/.alliasscheme/' . $allias . '.data.php';

        if (is_file($globalFile) && !is_file($localFile)) {
            $content = (string) file_get_contents($globalFile);
            // Можно добавить комментарий, что это локальная копия
            $content = "<?php\n// Local scheme for alias: {$allias}\n" . str_replace('<?php', '', $content);
            Fs::writeAtomic($localFile, $content);
        }

        return $this->redirect('/admin/?p=allias_scheme&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
    }

    public function delete(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }
        $postErr = $this->requirePost($request);
        if ($postErr !== null) {
            return $postErr;
        }
        $csrfErr = $this->requireCsrf($request);
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $domain = (string) $request->query('domain', '');
        $allias = (string) $request->query('allias', '');

        $localFile = $this->paths->domain($domain) . '/.alliasscheme/' . $allias . '.data.php';
        if (is_file($localFile)) {
            unlink($localFile);
        }

        return $this->redirect('/admin/?p=allias_scheme&domain=' . urlencode($domain) . '&allias=' . urlencode($allias));
    }
}
