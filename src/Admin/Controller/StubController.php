<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Element;
use Damoony\Ui\Field;
use Damoony\Ui\Form;
use Damoony\Ui\Message;
use Damoony\Ui\Table;
use Damoony\Ui\Text;

final class StubController extends BaseController
{
    public function __construct(
        \Damoony\Admin\AuthInterface $auth,
        \Damoony\Security\Csrf $csrf,
        View $view,
        private readonly Paths $paths,
    ) {
        parent::__construct($auth, $csrf, $view);
    }

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $stubDir = $this->paths->root() . '/stubs';
        $files = is_dir($stubDir) ? glob($stubDir . '/*.html') : [];

        $card = Builder::div('admin-card');
        $header = Builder::div('page-header');
        $header->append(Builder::h(1)->content(View::t('stub.title')));
        $actions = Builder::div('page-actions');
        $actions->append(Builder::a('/admin/?p=stub:new', 'btn btn-primary')->content(View::t('stub.add')));
        $header->append($actions);
        $card->append($header);

        if ($files === [] || $files === false) {
            $card->append(Message::info(View::t('stub.empty'))->build());
        } else {
            $table = (new Table())->headers(View::t('stub.th_name'), View::t('stub.th_actions'));
            foreach ($files as $file) {
                $name = basename($file, '.html');
                $actionsHtml = Builder::div('table-actions');
                $actionsHtml->append(Builder::a('/admin/?p=stub:edit&name=' . urlencode($name), 'btn btn-outline')->content(View::t('stub.edit')));

                $delUrl = '/admin/?p=stub:remove&name=' . urlencode($name);
                $delForm = Form::create($delUrl, 'POST')
                    ->csrf($this->csrf->field())
                    ->submit(View::t('stub.delete'));
                $delBtn = $delForm->build();
                foreach ($delBtn->children() as $child) {
                    if ($child instanceof Element && $child->tag() === 'button') {
                        $child->attrs()->classAdd('btn btn-danger');
                        $child->attrs()->styleAdd('padding:4px 10px;font-size:12px;');
                    }
                }
                $actionsHtml->append($delBtn);
                $table->row([Text::raw('<b>' . View::esc($name) . '</b>'), $actionsHtml]);
            }
            $card->append($table->build());
        }

        $backBtn = Builder::a('/admin/?p=domain', 'btn btn-outline')->content(View::t('stub.back'));
        $backBtn->attrs()->styleAdd('margin-top:20px;');
        $card->append($backBtn);

        return Response::html($this->view->render($card, ['title' => View::t('stub.title')]));
    }

    public function new(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $error = '';
        $nameVal = '';

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }

            $nameVal = (string) ($request->post['name'] ?? '');
            if (!preg_match('/^[a-z0-9_\-]+$/', $nameVal)) {
                $error = View::t('stub.err_name');
            } else {
                $file = $this->paths->root() . '/stubs/' . $nameVal . '.html';
                if (is_file($file)) {
                    $error = View::t('stub.err_exists');
                } else {
                    Fs::writeAtomic($file, "<!DOCTYPE html>\n<html>\n<head><meta charset=\"utf-8\"><title>{$nameVal}</title></head>\n<body>\n    <h1>{$nameVal}</h1>\n</body>\n</html>");
                    return $this->redirect('/admin/?p=stub:edit&name=' . urlencode($nameVal));
                }
            }
        }

        $form = Form::create('/admin/?p=stub:new', 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::text('name')->label(View::t('stub.name_label'))->required()->value($nameVal))
            ->submit(View::t('stub.create'));

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('stub.new_title')));
        if ($error !== '') {
            $card->append(Message::error($error)->build());
        }
        $card->append($form->build());

        return Response::html($this->view->render($card, ['title' => View::t('stub.new_title')]));
    }

    public function edit(Request $request): Response
    {
        $auth = $this->requireAuth();
        if ($auth !== null) {
            return $auth;
        }

        $name = (string) $request->query('name', '');
        $file = $this->paths->root() . '/stubs/' . $name . '.html';

        if ($name === '' || !is_file($file)) {
            return $this->redirect('/admin/?p=stub');
        }

        if ($request->isPost()) {
            $csrfErr = $this->requireCsrf($request);
            if ($csrfErr !== null) {
                return $csrfErr;
            }
            Fs::writeAtomic($file, (string) ($request->post['content'] ?? ''));
            return $this->redirect('/admin/?p=stub');
        }

        $form = Form::create('/admin/?p=stub:edit&name=' . urlencode($name), 'POST')
            ->csrf($this->csrf->field())
            ->add(Field::textarea('content')->value((string) file_get_contents($file))->rows(20))
            ->submit(View::t('stub.save'));

        $formNode = $form->build();
        foreach ($formNode->children() as $child) {
            if ($child instanceof \Damoony\Ui\Element && $child->tag() === 'textarea') {
                $child->attrs()->classAdd('code-editor');
            }
        }

        $card = Builder::div('admin-card');
        $card->append(Builder::a('/admin/?p=stub')->content(View::t('stub.back')));
        $card->append(Builder::h(1)->content(View::t('stub.edit_title') . ': ' . View::esc($name)));
        $card->append($formNode);

        return Response::html($this->view->render($card, ['title' => View::t('stub.edit_title')]));
    }

    public function remove(Request $request): Response
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
        $name = (string) $request->query('name', '');
        if ($name === '' || !preg_match('/^[a-z0-9_\-]+$/i', $name)) {
            return $this->redirect('/admin/?p=stub');
        }
        $file = $this->paths->root() . '/stubs/' . $name . '.html';
        if (is_file($file)) {
            unlink($file);
        }
        return $this->redirect('/admin/?p=stub');
    }
}
