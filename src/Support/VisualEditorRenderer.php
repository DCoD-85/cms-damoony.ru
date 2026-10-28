<?php

declare(strict_types=1);

namespace Damoony\Admin\Support;

use Damoony\Admin\View;
use Damoony\Ui\Builder;

final class VisualEditorRenderer
{
    /**
     * Рендерит строку для визуального редактора страниц и блоков
     *
     * @param array<string, mixed> $block
     */
    public static function renderBlockRow(int $index, array $block): string
    {
        $type = (string)($block['type'] ?? 'text');
        $content = (string)($block['content'] ?? '');

        $row = Builder::div('editor-row');

        $select = Builder::el('select', false);
        $select->attrs()->classAdd('type-select');
        $select->attrs()->add('name', "blocks[{$index}][type]");

        $options = [
            'h1' => View::t('page.type_h1'),
            'h2' => View::t('page.type_h2'),
            'text' => View::t('page.type_text'),
            'img' => View::t('page.type_img'),
        ];
        foreach ($options as $val => $label) {
            $opt = Builder::el('option');
            $opt->attrs()->add('value', $val);
            if ($type === $val) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content($label);
            $select->append($opt);
        }
        $row->append($select);

        $input = Builder::el('input', true);
        $input->attrs()->classAdd('content-input');
        $input->attrs()->add('type', 'text');
        $input->attrs()->add('name', "blocks[{$index}][content]");
        $input->attrs()->add('value', $content);
        $input->attrs()->add('placeholder', View::t('page.block_content'));
        $row->append($input);

        $btn = Builder::el('button', false);
        $btn->attrs()->classAdd('btn btn-danger remove-block-btn');
        $btn->attrs()->classAdd('remove-btn');
        $btn->attrs()->add('type', 'button');
        $btn->content('✕');
        $row->append($btn);

        return $row->release();
    }

    /**
     * Рендерит строку для визуального редактора схем
     *
     * @param array<string, mixed> $item
     * @param list<string> $availableSlots
     */
    public static function renderSlotRow(int $index, array $item, array $availableSlots): string
    {
        $slotName = (string)($item['slot'] ?? 'center');
        $tag = (string)($item['tag'] ?? 'div');
        $class = (string)($item['class'] ?? '');

        $row = Builder::div('editor-row');

        // Кнопки перемещения
        $moveDiv = Builder::div('move-buttons');

        $upBtn = Builder::el('button', false);
        $upBtn->attrs()->classAdd('btn btn-outline move-up-btn');
        $upBtn->attrs()->add('type', 'button');
        $upBtn->content('▲');
        $moveDiv->append($upBtn);

        $downBtn = Builder::el('button', false);
        $downBtn->attrs()->classAdd('btn btn-outline move-down-btn');
        $downBtn->attrs()->add('type', 'button');
        $downBtn->content('▼');
        $moveDiv->append($downBtn);

        $row->append($moveDiv);

        // Слот
        $selectSlot = Builder::el('select', false);
        $selectSlot->attrs()->classAdd('type-select');
        $selectSlot->attrs()->add('name', "slots[{$index}][name]");
        foreach ($availableSlots as $as) {
            $opt = Builder::el('option');
            $opt->attrs()->add('value', $as);
            if ($slotName === $as) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content($as);
            $selectSlot->append($opt);
        }
        $row->append($selectSlot);

        // Тег
        $selectTag = Builder::el('select', false);
        $selectTag->attrs()->classAdd('tag-select');
        $selectTag->attrs()->add('name', "slots[{$index}][tag]");
        foreach (['div', 'header', 'main', 'footer', 'aside', 'section', 'article'] as $t) {
            $opt = Builder::el('option');
            $opt->attrs()->add('value', $t);
            if ($tag === $t) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content('<' . $t . '>');
            $selectTag->append($opt);
        }
        $row->append($selectTag);

        // CSS класс
        $inputClass = Builder::el('input', true);
        $inputClass->attrs()->classAdd('content-input');
        $inputClass->attrs()->add('type', 'text');
        $inputClass->attrs()->add('name', "slots[{$index}][class]");
        $inputClass->attrs()->add('value', $class);
        $inputClass->attrs()->add('placeholder', 'CSS класс');
        $row->append($inputClass);

        // Удалить
        $btn = Builder::el('button', false);
        $btn->attrs()->classAdd('btn btn-danger remove-slot-btn');
        $btn->attrs()->classAdd('remove-btn');
        $btn->attrs()->add('type', 'button');
        $btn->content('✕');
        $row->append($btn);

        return $row->release();
    }
}
