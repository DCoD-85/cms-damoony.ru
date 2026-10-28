<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Support\Markdown;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Ui\Text;

class algoritm_doc extends Algoritm
{
    public function default(): Node|Response|null
    {
        $root = dirname((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
        $docDir = $root . '/_doc';
        
        $file = $this->args['doc'] ?? 'README.md';
        $file = basename($file);
        if (!str_ends_with($file, '.md')) {
            $file .= '.md';
        }
        
        $filePath = $docDir . '/' . $file;
        
        $container = Builder::div('doc-layout');
        
        // ... (сборка sidebar и content без изменений)
        $sidebar = Builder::div('doc-sidebar');
        $sidebar->append(Builder::h(2)->content('Документация'));
        $nav = Builder::ul('doc-nav');
        $files = glob($docDir . '/*.md') ?: [];
        sort($files);
        foreach ($files as $f) {
            $name = basename($f);
            $li = Builder::li();
            $a = Builder::a('/' . $name);
            $a->content($name);
            if ($name === $file) $li->attrs()->classAdd('active');
            $li->append($a);
            $nav->append($li);
        }
        $sidebar->append($nav);
        
        $content = Builder::div('doc-content');
        if (is_file($filePath)) {
            $raw = (string) file_get_contents($filePath);
            $content->append(Text::raw(Markdown::render($raw)));
        } else {
            $content->append(Builder::h(1)->content('404'));
            $content->append(Builder::p()->content('Файл ' . $file . ' не найден.'));
        }
        
        $container->append($sidebar);
        $container->append($content);

        // --- УПРАВЛЕНИЕ РЕГИОНАМИ ИЗ КОДА ---
        
        // 1. Отключаем шапку на этой странице (если она не отключена в конфиге)
        # $this->regions()->disable('top');
        
        // 2. Подменяем регион center на наш контейнер
        $this->setContent('center', $container);

        // 3. Загружаем схему и рендерим
        $paths = $this->context->service('paths');
        if (!$paths instanceof \Damoony\Files\Paths) {
            $paths = new \Damoony\Files\Paths(dirname(__DIR__, 6));
        }

        $schemeLoader = new \Damoony\Scheme\SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);

        $renderer = new \Damoony\Scheme\SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}