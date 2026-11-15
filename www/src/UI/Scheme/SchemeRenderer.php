<?php

declare(strict_types=1);

namespace Damoony\UI\Scheme;

use Damoony\UI\Element;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;
use Damoony\UI\Slot;

final class SchemeRenderer
{
    /**
     * @param array<string, mixed> $skeleton
     */
    public function render(array $skeleton, RegionRegistry $regions, RegionContext $context): Element
    {
        // Мета
        if (isset($skeleton['meta']) && is_array($skeleton['meta'])) {
            $meta = $skeleton['meta'];
            if (isset($meta['title'])) {
                $context->meta->setTitle((string) $meta['title']);
            }
            $skin = $this->resolveSkin($context);
            if (isset($meta['css']) && is_array($meta['css'])) {
                foreach ($meta['css'] as $css) {
                    $context->meta->addCss($this->substituteTokens((string) $css, $context, $skin));
                }
            }
            if (isset($meta['js']) && is_array($meta['js'])) {
                foreach ($meta['js'] as $js) {
                    $context->meta->addJs($this->substituteTokens((string) $js, $context, $skin));
                }
            }
        }

        $root = Element::fromArray($skeleton);
        $this->replaceSlots($root, $regions, $context);

        return $root;
    }

    private function replaceSlots(Element $el, RegionRegistry $regions, RegionContext $context): void
    {
        $newChildren = [];

        foreach ($el->children() as $child) {
            if ($child instanceof Slot) {
                $region = $regions->get($child->name());
                if ($region !== null && $region->enabled()) {
                    $wrapper = new Element('div');
                    $wrapper->attrs()->add('data-block', $child->name());
                    $wrapper->append($region->render($context));
                    $newChildren[] = $wrapper;
                }
            } elseif ($child instanceof Element) {
                $this->replaceSlots($child, $regions, $context);
                $newChildren[] = $child;
            } else {
                $newChildren[] = $child;
            }
        }

        $el->clear();
        foreach ($newChildren as $c) {
            $el->append($c);
        }
    }

    private function substituteTokens(string $url, RegionContext $context, string $skin): string
    {
        return str_replace(
            ['{skin}', '{real}', '{alias}'],
            [$skin, $context->domain->real, $context->domain->allias],
            $url,
        );
    }

    private function resolveSkin(RegionContext $context): string
    {
        $requested = (string) $context->domain->skin;
        $paths = $context->service('paths');
        if (!$paths instanceof \Damoony\Core\Files\Paths) {
            return $requested !== '' ? $requested : 'default';
        }

        $manager = new \Damoony\UI\Skin\SkinManager($paths->mediaRoot());
        if ($manager->exists($context->domain->real, $context->domain->allias, $requested)) {
            return $requested;
        }
        if ($manager->exists($context->domain->real, $context->domain->allias, 'default')) {
            return 'default';
        }

        return $requested !== '' ? $requested : 'default';
    }
}
