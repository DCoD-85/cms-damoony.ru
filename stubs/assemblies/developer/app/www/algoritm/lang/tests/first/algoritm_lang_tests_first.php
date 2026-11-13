<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
use Damoony\Core\Http\Response;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Button;

final class algoritm_lang_tests_first extends Page
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
        $className = '\\{{namespace}}\\{{alias}}\\Test_itemsProvider';
        $provider = new $className($db, $site, $this->context->domain->real, $this->context->domain->allias);

        if ($this->isPost()) {
            $title = (string) $this->context->request->post('title');
            $body = (string) $this->context->request->post('body');
            if ($title !== '') {
                $provider->create(['title' => $title, 'body' => $body]);
                $flash = $this->context->service('flash');
                if ($flash instanceof \Damoony\Core\Flash) {
                    $flash->add('success', $this->context->lang('tests.success'));
                }
            }
            if (!$this->isAjax()) { return Response::redirect($this->context->request->path); }
        }

        $wrap = Builder::div();
        $wrap->append(Builder::h(1)->content($this->context->lang('tests.first_title')));
        
        $form = new Form(); $form->method('POST');
        $form->add(new Input('text', 'title'));
        $form->add(new Input('text', 'body'));
        $form->add(new Button($this->context->lang('tests.add_item')));
        $wrap->append($form);

        $items = $provider->get([], 50);
        $ul = Builder::ul();
        foreach ($items as $item) { $ul->append(Builder::li()->content('#' . $item['id'] . ' ' . $item['title'])); }
        $wrap->append($ul);

        return $wrap;
    }
}