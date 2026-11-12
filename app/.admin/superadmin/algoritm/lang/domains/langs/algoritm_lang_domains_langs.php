<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Algoritm\AdminPage;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\UI\Builder;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Card;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Td;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tr;
use Damoony\UI\Node;

final class algoritm_lang_domains_langs extends AdminPage
{
    protected function content(): Node|Response|null
    {
        $lang = (string) ($this->args['lang'] ?? 'ru');
        $domain = (string) ($this->args['domain'] ?? '');
        $alias = (string) ($this->args['allias'] ?? '');

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths missing');
        }

        $phpBin = PHP_BINARY;
        if (str_ends_with($phpBin, 'php-cgi.exe')) {
            $phpBin = str_replace('php-cgi.exe', 'php.exe', $phpBin);
        }
        $cliPath = $paths->root() . '/bin/cli.php';

        /** @param array<string, string> $args */
        $runCli = function (string $command, array $args) use ($phpBin, $cliPath): array {
            $parts = [escapeshellarg($phpBin), escapeshellarg($cliPath), $command];
            foreach ($args as $k => $v) {
                $parts[] = '--' . $k . '=' . escapeshellarg($v);
            }
            $cmd = implode(' ', $parts) . ' 2>&1';
            $out = [];
            $ret = 0;
            exec($cmd, $out, $ret);
            $json = json_decode(implode("
", $out), true);
            return is_array($json) ? $json : ['ok' => false, 'errors' => ['invalid CLI output'], 'data' => []];
        };

        $baseUrl = '/admin/' . $lang . '/domains/langs';
        if ($domain !== '') {
            $baseUrl .= '/' . rawurlencode($domain);
            if ($alias !== '') {
                $baseUrl .= '/' . rawurlencode($alias);
            }
        }

        if ($this->context->request->isPost()) {
            $action = (string) $this->context->request->post('action', '');
            $langCode = (string) $this->context->request->post('lang_code', '');

            $cliArgs = ['domain' => $domain];
            if ($alias !== '') {
                $cliArgs['allias'] = $alias;
            }

            $cmd = '';
            if ($action === 'add') {
                $cmd = 'lang:add';
                $cliArgs['lang'] = $langCode;
            } elseif ($action === 'remove') {
                $cmd = 'lang:remove';
                $cliArgs['lang'] = $langCode;
                $cliArgs['force'] = '1';
            } elseif ($action === 'repair') {
                $cmd = 'lang:repair';
                $cliArgs['fix'] = '1';
            }

            if ($cmd !== '') {
                $res = $runCli($cmd, $cliArgs);
                $ok = ($res['ok'] ?? false) === true;
                $errors = (array) ($res['errors'] ?? []);
                $msg = $ok ? 'OK: ' . $cmd : 'Ошибка: ' . implode('; ', array_map('strval', $errors));

                if ($this->context->request->isAjax()) {
                    $struct = new \Damoony\UI\Response\MinStruct();
                    $struct->ok($ok);
                    $struct->message($ok ? 'success' : 'error', $msg);
                    $struct->form(false, [], $baseUrl); // JS сделает soft-redirect на $baseUrl
                    return $struct->toResponse();
                }

                $_SESSION['flash'][] = [
                    'type' => $ok ? 'success' : 'error',
                    'message' => $msg,
                ];
                return Response::redirect($baseUrl);
            }
        }

        $content = Builder::div('');
        $content->append(Builder::h(2)->content('Языки'));

        if ($domain === '') {
            $res = $runCli('domain:list', ['json' => '1']);
            /** @var list<array<string, mixed>> $domainRows */
            $domainRows = (array) ($res['data']['rows'] ?? []);
            $ul = Builder::ul('domains-list');
            foreach ($domainRows as $row) {
                $d = (string) ($row['domain'] ?? '');
                if ($d === '') {
                    continue;
                }
                $li = Builder::li();
                $li->append(new Link('/admin/' . $lang . '/domains/langs/' . rawurlencode($d), $d));
                $aliases = (array) ($row['aliases'] ?? []);
                if ($aliases !== []) {
                    $li->append(Builder::span()->content(' — ' . implode(', ', array_map('strval', $aliases))));
                }
                $ul->append($li);
            }
            $content->append($ul);
            $this->setContent('center', $content);
            return $content;
        }

        $content->append(Builder::h(3)->content('Домен: ' . $domain . ($alias !== '' ? ' / ' . $alias : '')));

        if ($alias === '') {
            $domRes = $runCli('domain:list', ['json' => '1']);
            $domRows = (array) ($domRes['data']['rows'] ?? []);
            $aliases = [];
            foreach ($domRows as $dr) {
                if ((string) ($dr['domain'] ?? '') === $domain) {
                    $aliases = (array) ($dr['aliases'] ?? []);
                    break;
                }
            }
            if ($aliases !== []) {
                $content->append(Builder::h(4)->content('Алиасы'));
                $al = Builder::ul('aliases-list');
                foreach ($aliases as $a) {
                    $a = (string) $a;
                    if ($a === '') {
                        continue;
                    }
                    $al->append(Builder::li()->append(
                        new Link('/admin/' . $lang . '/domains/langs/' . rawurlencode($domain) . '/' . rawurlencode($a), $a)
                    ));
                }
                $content->append($al);
            }
        }

        $listArgs = ['domain' => $domain, 'with-config' => '1', 'json' => '1'];
        if ($alias !== '') {
            $listArgs['allias'] = $alias;
        }
        $res = $runCli('lang:list', $listArgs);
        $data = (array) ($res['data'] ?? []);
        /** @var list<array<string, mixed>> $rows */
        $rows = (array) ($data['rows'] ?? []);

        $table = new Table();
        $thead = new Thead();
        $trHead = new Tr();
        $trHead->add(new Th('Язык'));
        $trHead->add(new Th('Файл'));
        $trHead->add(new Th('В config'));
        $trHead->add(new Th('Действия'));
        $thead->add($trHead);
        $table->add($thead);

        $tbody = new Tbody();
        if ($rows === []) {
            $tr = new Tr();
            $td = new Td();
            $td->attrs()->add('colspan', '4');
            $td->content('Языков нет');
            $tr->add($td);
            $tbody->add($tr);
        } else {
            foreach ($rows as $row) {
                $rowLang = (string) ($row['lang'] ?? '');
                $tr = new Tr();
                $tr->add((new Td())->content($rowLang));
                $tr->add((new Td())->content(is_string($row['file'] ?? null) ? $row['file'] : '—'));
                $tr->add((new Td())->content(($row['in_config'] ?? false) === true ? 'yes' : 'no'));

                $removeForm = new Form();
                $removeForm->action($baseUrl)->method('POST');
                $removeForm->add(new Input('hidden', 'action', 'remove'));
                $removeForm->add(new Input('hidden', 'lang_code', $rowLang));
                $removeForm->add(new Button('Удалить'));
                $tr->add((new Td())->add($removeForm));
                $tbody->add($tr);
            }
        }
        $table->add($tbody);
        $content->append($table);

        $addCard = new Card('Добавить язык');
        $addForm = new Form();
        $addForm->action($baseUrl)->method('POST');
        $addForm->add(new Input('hidden', 'action', 'add'));
        $addForm->add(new Label('Код языка (ISO 639-1)'));
        $addForm->add(new Input('text', 'lang_code', ''));
        $addForm->add(new Button('Добавить'));
        $addCard->add($addForm);
        $content->append($addCard);

        $this->setContent('center', $content);
        return $content;
    }
}
