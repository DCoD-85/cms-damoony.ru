<?php

declare(strict_types=1);

namespace damoony4_ru\www;

use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;
use Damoony\UI\Response\MinStruct;

final class algoritm_lang_testscheme extends Page
{
    protected function content(): Node|Response|null
    {
        $paths = $this->context->service('paths');
        $site = $this->context->service('site');
        $db = $this->context->service('db');
        if (!$db instanceof \Damoony\Data\Db\Connection) { return Response::internalError('DB missing'); }
        $providerFile = $paths->allias($this->context->domain->real, $this->context->domain->allias) . '/.dbprovider/Test_itemsProvider.php';
        if (!is_file($providerFile)) { return Builder::div()->content('Provider not found'); }
        require_once $providerFile;
        $className = '\\damoony4_ru\\www\\Test_itemsProvider';
        $provider = new $className($db, $site, $this->context->domain->real, $this->context->domain->allias);

        if ($this->isPost()) {
            $title = (string) $this->context->request->post('title');
            $body = (string) $this->context->request->post('body');
            if ($title !== '') {
                $provider->create(['title' => $title, 'body' => $body]);
            }
            if ($this->isAjax()) {
                $struct = new MinStruct();
                $struct->ok(true);
                $struct->release($this->renderList($provider)->release());
                return $struct->toResponse();
            }
            return Response::redirect($this->context->request->path);
        }

        $wrap = Builder::div('testscheme-wrapper');
        $wrap->append(Builder::h(1)->content('Test Scheme: DB CRUD'));
        $wrap->append($this->renderForm());
        $wrap->append($this->renderList($provider));
        return $wrap;
    }

    private function renderForm(): Node
    {
        $form = new Form();
        $form->action('')->method('POST');
        $title = new Input('text', 'title');
        $title->attrs()->add('placeholder', 'Title');
        $title->attrs()->add('required', 'required');
        $form->add($title);
        $body = new Input('text', 'body');
        $body->attrs()->add('placeholder', 'Body');
        $form->add($body);
        $form->add(new Button('Add Item'));
        return $form;
    }

    private function renderList($provider): Node
    {
        $items = $provider->get([], 50);
        $ul = Builder::ul('test-items-list');
        foreach ($items as $item) {
            $ul->append(Builder::li()->content('#' . $item['id'] . ' - ' . $item['title'] . ' (' . $item['created_at'] . ')'));
        }
        return $ul;
    }
}
