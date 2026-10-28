<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Diagnostics\StructureChecker;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Ui\Builder;

final class OverviewController extends BaseController
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

        $checker = new StructureChecker($this->paths, $this->siteConfig);
        $domains = $this->siteConfig->getByKey(['domains_real'], []);
        $aliasesMap = $this->siteConfig->getByKey(['alliases_real'], []);

        $card = Builder::div('admin-card');
        $card->append(Builder::h(1)->content(View::t('overview.title')));

        $subtitle = Builder::p()->content(View::t('overview.subtitle'));
        $subtitle->attrs()->styleAdd('color: #6b7280; margin-bottom: 30px;');
        $card->append($subtitle);

        $grid = Builder::div();
        $grid->attrs()->styleAdd('display: flex; flex-direction: column; gap: 20px;');

        if (is_array($domains)) {
            foreach ($domains as $incoming => $realDomain) {
                if ($incoming !== $realDomain) {
                    continue; // Показываем только реальные домены
                }

                $domainCard = Builder::div();
                $domainCard->attrs()->styleAdd('border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;');

                $domainHeader = Builder::div();
                $domainHeader->attrs()->styleAdd('background: #f9fafb; padding: 15px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;');

                $h2 = Builder::h(2)->content('Домен: ' . View::esc($realDomain));
                $h2->attrs()->styleAdd('margin: 0; font-size: 18px;');
                $domainHeader->append($h2);

                $btn = Builder::a('/admin/?p=domain:edit&domain=' . urlencode($realDomain), 'btn btn-outline')->content('Настройки');
                $btn->attrs()->styleAdd('padding: 4px 10px; font-size: 12px;');
                $domainHeader->append($btn);

                $domainCard->append($domainHeader);

                $aliases = $aliasesMap[$realDomain] ?? [];
                $aliasesContainer = Builder::div();
                $aliasesContainer->attrs()->styleAdd('padding: 15px; display: flex; flex-direction: column; gap: 10px;');

                if (is_array($aliases) && $aliases !== []) {
                    foreach ($aliases as $aliasName => $mapped) {
                        if ($aliasName === '') {
                            continue;
                        }

                        $issues = $checker->checkAllias($realDomain, (string)$aliasName);

                        $statusColor = '#10b981'; // Green
                        $statusText = 'OK';
                        if ($issues !== []) {
                            $hasError = false;
                            foreach ($issues as $issue) {
                                if ($issue['level'] === 'error') {
                                    $hasError = true;
                                }
                            }
                            $statusColor = $hasError ? '#ef4444' : '#f59e0b'; // Red or Yellow
                            $statusText = $hasError ? 'Ошибка' : 'Внимание';
                        }

                        $aliasRow = Builder::div();
                        $aliasRow->attrs()->styleAdd('display: flex; align-items: center; justify-content: space-between; padding: 10px; border: 1px solid #f3f4f6; border-radius: 6px; background: #fff;');

                        $info = Builder::div();
                        $info->attrs()->styleAdd('display: flex; align-items: center; gap: 15px;');

                        $badge = Builder::span();
                        $badge->attrs()->styleAdd("background: {$statusColor}; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;");
                        $badge->content($statusText);
                        $info->append($badge);

                        $aliasLabel = Builder::span()->content('Алиас: ' . View::esc((string)$aliasName));
                        $aliasLabel->attrs()->styleAdd('font-weight: bold; font-size: 15px;');
                        $info->append($aliasLabel);

                        $links = Builder::div();
                        $links->attrs()->styleAdd('display: flex; gap: 5px;');

                        $linkPages = Builder::a('/admin/?p=page&domain=' . urlencode($realDomain) . '&allias=' . urlencode((string)$aliasName), 'btn btn-outline')->content('Страницы');
                        $linkPages->attrs()->styleAdd('padding: 4px 10px; font-size: 12px;');
                        $links->append($linkPages);

                        $linkSchemes = Builder::a('/admin/?p=scheme&domain=' . urlencode($realDomain), 'btn btn-outline')->content('Схемы');
                        $linkSchemes->attrs()->styleAdd('padding: 4px 10px; font-size: 12px;');
                        $links->append($linkSchemes);

                        $linkBlocks = Builder::a('/admin/?p=filler&domain=' . urlencode($realDomain) . '&allias=' . urlencode((string)$aliasName), 'btn btn-outline')->content('Блоки');
                        $linkBlocks->attrs()->styleAdd('padding: 4px 10px; font-size: 12px;');
                        $links->append($linkBlocks);

                        $aliasRow->append($info);
                        $aliasRow->append($links);

                        if ($issues !== []) {
                            $errorsBox = Builder::div();
                            $errorsBox->attrs()->styleAdd('width: 100%; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb; font-size: 13px; color: #4b5563;');
                            foreach ($issues as $issue) {
                                $icon = $issue['level'] === 'error' ? '🔴' : '🟡';
                                $errorsBox->append(Builder::div()->content($icon . ' ' . $issue['message']));
                            }

                            $wrapper = Builder::div();
                            $wrapper->attrs()->styleAdd('display: flex; flex-wrap: wrap; width: 100%;');
                            $wrapper->append($aliasRow);
                            $wrapper->append($errorsBox);
                            $aliasesContainer->append($wrapper);
                        } else {
                            $aliasesContainer->append($aliasRow);
                        }
                    }
                } else {
                    $p = Builder::p()->content('Нет алиасов');
                    $p->attrs()->styleAdd('color: #9ca3af;');
                    $aliasesContainer->append($p);
                }

                $domainCard->append($aliasesContainer);
                $grid->append($domainCard);
            }
        }

        $card->append($grid);
        return Response::html($this->view->render($card, ['title' => View::t('overview.title')]));
    }
}
