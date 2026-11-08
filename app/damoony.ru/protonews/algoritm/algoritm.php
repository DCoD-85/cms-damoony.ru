<?php

declare(strict_types=1);

namespace damoony_ru\protonews;

require_once __DIR__ . '/../provider/ArticlesProvider.php';
require_once __DIR__ . '/../provider/CategoriesProvider.php';


use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\Data\Db\Connection;
use Damoony\Core\Config;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $db = $this->context->service('db');
        $site = $this->context->service('site');
        
        if (!$db instanceof Connection) {
            return Response::internalError('DB missing');
        }
        
        $articlesProvider = new ArticlesProvider($db, $site, $this->context->domain->real, 'protonews');
        
        try {
            $articles = $articlesProvider->getWithCategory(10);
        } catch (\Throwable $e) {
            return Builder::div('error')->content('Ошибка БД: ' . $e->getMessage());
        }
        
        $content = Builder::div('news-list');
        $content->append(Builder::h(1)->content('Protonews Demo'));
        
        if (empty($articles)) {
            $content->append(Builder::p()->content('Новостей пока нет.'));
        } else {
            foreach ($articles as $article) {
                $card = Builder::div('news-item');
                $card->append(Builder::div('news-meta')->content($article['category_name'] ?? 'Без категории'));
                $card->append(Builder::h(2)->content($article['title']));
                $card->append(Builder::p()->content(mb_substr((string)$article['content'], 0, 200) . '...'));
                $content->append($card);
            }
        }
        
        return $content;
    }
}
