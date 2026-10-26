<?php

declare(strict_types=1);

namespace damoony_ru\doc;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
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
            if ($name === $file) {
                $li->attrs()->classAdd('active');
            }
            $li->append($a);
            $nav->append($li);
        }
        $sidebar->append($nav);
        
        $content = Builder::div('doc-content');
        if (is_file($filePath)) {
            $raw = (string) file_get_contents($filePath);
            $html = Markdown::render($raw);
            $content->append(Text::raw($html));
        } else {
            $content->append(Builder::h(1)->content('404'));
            $content->append(Builder::p()->content('Файл ' . $file . ' не найден.'));
        }
        
        $container->append($sidebar);
        $container->append($content);
        
        $style = Builder::el('style')->content(self::css());
        $container->prepend($style);

        // --- ИНТЕГРАЦИЯ СО СХЕМОЙ ---
        // Берем Paths из контекста
        $paths = $this->context->service('paths');
        if (!$paths instanceof \Damoony\Files\Paths) {
            $paths = new \Damoony\Files\Paths(dirname(__DIR__, 6));
        }

        // Загружаем регионы (включая top_doc)
        $regionLoader = new \Damoony\Region\RegionLoader($paths);
        $regions = $regionLoader->load($this->context->domain);

        // Создаем виртуальный регион для слота center с нашим doc-layout
        $regions->add(new class($container) implements \Damoony\Region\RegionInterface {
            public function __construct(private Node $node) {}
            public function name(): string { return 'center'; }
            public function enabled(): bool { return true; }
            public function render(\Damoony\Region\RegionContext $context): Node { return $this->node; }
        });

        // Загружаем схему
        $schemeLoader = new \Damoony\Scheme\SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, 'www');

        $renderer = new \Damoony\Scheme\SchemeRenderer($regions);

        return $renderer->render($manifest, $this->context);
    }

    private static function css(): string
    {
        return '
        .doc-layout { display: flex; max-width: 1200px; margin: 40px auto; gap: 40px; font-family: system-ui, sans-serif; padding: 0 20px; }
        .doc-sidebar { width: 250px; flex-shrink: 0; }
        .doc-sidebar h2 { font-size: 20px; margin-bottom: 16px; }
        .doc-nav { list-style: none; padding: 0; margin: 0; }
        .doc-nav li { margin-bottom: 8px; }
        .doc-nav a { text-decoration: none; color: #0071e3; }
        .doc-nav li.active a { font-weight: bold; color: #1d1d1f; }
        .doc-content { flex-grow: 1; min-width: 0; }
        .doc-content pre { background: #f5f5f7; padding: 16px; border-radius: 8px; overflow-x: auto; }
        .doc-content code { font-family: monospace; background: #f5f5f7; padding: 2px 6px; border-radius: 4px; }
        .doc-content pre code { background: transparent; padding: 0; }
        .doc-content h1, .doc-content h2, .doc-content h3 { margin-top: 24px; }
        .doc-content p { line-height: 1.6; color: #333; }
        ';
    }
}