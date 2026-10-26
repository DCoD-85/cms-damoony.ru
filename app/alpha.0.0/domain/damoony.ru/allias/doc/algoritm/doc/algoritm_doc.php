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
        
        $style = Builder::el('style');
        $container->prepend($style);

        // --- ИНТЕГРАЦИЯ СО СХЕМОЙ ---
        $paths = $this->context->service('paths');
        if (!$paths instanceof \Damoony\Files\Paths) {
            $paths = new \Damoony\Files\Paths(dirname(__DIR__, 6));
        }

        $regionLoader = new \Damoony\Region\RegionLoader($paths);
        $regions = $regionLoader->load($this->context->domain);

        $regions->add(new class($container) implements \Damoony\Region\RegionInterface {
            public function __construct(private Node $node) {}
            public function name(): string { return 'center'; }
            public function enabled(): bool { return true; }
            public function render(\Damoony\Region\RegionContext $context): Node { return $this->node; }
        });

        $schemeLoader = new \Damoony\Scheme\SchemeLoader($paths);
        // Используем динамическое имя схемы
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);

        $renderer = new \Damoony\Scheme\SchemeRenderer($regions);

        return $renderer->render($manifest, $this->context);
    }
}