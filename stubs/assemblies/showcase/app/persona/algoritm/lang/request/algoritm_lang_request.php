<?php

declare(strict_types=1);

namespace {{namespace}}\persona;

use Damoony\Core\Http\Response;
use Damoony\Core\Security\Csrf;
use Damoony\Data\Persona\PersonaAuth;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;

final class algoritm_lang_request extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $repo = $this->context->service('persona');
        if (!$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $sent = ($this->context->request->query['sent'] ?? '') === '1';

        if ($this->isPost()) {
            $name = trim((string) $this->context->request->post('name'));
            $email = strtolower(trim((string) $this->context->request->post('email')));
            $comment = trim((string) $this->context->request->post('comment'));
            $projects = (array) ($this->context->request->post('projects') ?? []);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $flash = $this->context->service('flash');
                if ($flash instanceof \Damoony\Core\Flash) {
                    $flash->add('error', $this->context->lang('persona.error_email'));
                }

                return Response::redirect('/' . $lang . '/request/');
            }

            $projects = array_values(array_filter(
                array_map(static fn ($p): string => (string) $p, $projects),
                static fn (string $p): bool => in_array($p, ['developer', 'fmanager', 'simpleworlds'], true),
            ));

            $repo->createRequest($name, $email, $comment, $projects);

            $paths = $this->context->service('paths');
            if ($paths instanceof \Damoony\Core\Files\Paths) {
                (new \Damoony\Data\Persona\AdminNotifier($paths))->notify(
                    'Новая заявка на регистрацию: ' . $email . ' (' . $name . ')',
                    'info',
                    'persona',
                );
            }

            return Response::redirect('/' . $lang . '/request/?sent=1');
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('persona.request_title')));

        if ($sent) {
            $wrap->append(Builder::p('persona-success')->content($this->context->lang('persona.request_sent')));
        }

        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('persona.back')),
        ));

        $form = new Form();
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof Csrf) {
            $form->csrf($csrf);
        }
        $form->add(new Input('text', 'name'));
        $form->add(new Input('email', 'email'));
        $form->add(new Input('text', 'comment'));
        foreach (['developer', 'fmanager', 'simpleworlds'] as $project) {
            $form->add(new Input('checkbox', 'projects[]', $project));
        }
        $form->add(new Button($this->context->lang('persona.request_submit')));
        $wrap->append($form);

        return $wrap;    }
}
