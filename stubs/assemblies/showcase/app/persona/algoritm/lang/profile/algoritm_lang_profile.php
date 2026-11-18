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

final class algoritm_lang_profile extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;
        $auth = $this->context->service('persona_auth');
        $repo = $this->context->service('persona');
        if (!$auth instanceof PersonaAuth || !$repo instanceof PersonaRepository) {
            return Response::notFound();
        }

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/' . $lang . '/');
        }

        $accountId = (int) $user['id'];

        if ($this->isPost()) {
            $project = trim((string) $this->context->request->post('project'));
            if (in_array($project, ['developer', 'fmanager', 'simpleworlds'], true)) {
                $existing = $repo->member($accountId, $project);
                if ($existing === [] || ($existing['status'] ?? '') === 'pending') {
                    $repo->createMember($accountId, $project, '', 'user', 'pending');
                    $repo->setMemberStatus((int) $repo->member($accountId, $project)['id'], 'pending');

                    $paths = $this->context->service('paths');
                    if ($paths instanceof \Damoony\Core\Files\Paths) {
                        (new \Damoony\Data\Persona\AdminNotifier($paths))->notify(
                            'Заявка на доступ: ' . (string) $user['email'] . ' → ' . $project,
                            'info',
                            'persona',
                        );
                    }
                }
            }

            return Response::redirect('/' . $lang . '/profile/');
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content($this->context->lang('persona.profile')));
        $wrap->append(Builder::p()->content((string) $user['email']));

        $members = $repo->membersFor($accountId);
        if ($members !== []) {
            $list = Builder::ul('persona-members');
            foreach ($members as $member) {
                $list->append(Builder::li()->content(
                    (string) $member['project']
                    . ((string) $member['alias'] !== '' ? '/' . (string) $member['alias'] : '')
                    . ' — ' . (string) $member['role'] . ' (' . (string) $member['status'] . ')',
                ));
            }
            $wrap->append($list);
        }

        $wrap->append(Builder::h(2)->content($this->context->lang('persona.projects')));
        foreach (['developer', 'fmanager', 'simpleworlds'] as $project) {
            $member = $repo->member($accountId, $project);
            $status = $member === [] ? $this->context->lang('persona.access_none') : (string) $member['status'];

            if ($status !== 'active') {
                $form = new Form('persona-access-form');
                $form->method('POST');
                $csrf = $this->context->service('csrf');
                if ($csrf instanceof Csrf) {
                    $form->csrf($csrf);
                }
                $hidden = new Input('hidden', 'project', $project);
                $form->add($hidden);
                $form->add(new Button($this->context->lang('persona.request_access') . ': ' . $project));
                $wrap->append($form);
            } else {
                $wrap->append(Builder::p()->content($project . ' — active'));
            }
        }

        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/logout/')->content($this->context->lang('persona.logout')),
        ));

        return $wrap;    }
}
