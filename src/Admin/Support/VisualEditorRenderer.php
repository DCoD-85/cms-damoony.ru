<?php

declare(strict_types=1);

namespace Damoony\Admin\Support;

use Damoony\Admin\View;
use Damoony\Ui\Builder;
use Damoony\Ui\Text;

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

    /**
     * Рендерит полную форму визуального редактора страницы
     *
     * @param list<array{type: string, content: string}> $blocks
     * @param list<string> $hiddenSlots
     * @param list<string> $availableSlots
     */
    public static function renderPageForm(string $action, string $csrfField, array $blocks, array $hiddenSlots, string $contentSlot, array $availableSlots): string
    {
        $form = Builder::el('form');
        $form->attrs()->add('method', 'post');
        $form->attrs()->add('action', $action);
        $form->attrs()->classAdd('editor-form');
        $form->append(Text::raw($csrfField));

        // 1. Настройки контента (слот + скрытие)
        $settingsBox = Builder::div('editor-row');
        $settingsBox->attrs()->styleAdd('flex-wrap: wrap;');

        // Слот
        $slotGroup = Builder::div();
        $slotGroup->attrs()->styleAdd('display:flex; flex-direction:column; gap:5px;');
        $slotGroup->append(Builder::el('label')->content('Куда вставлять контент'));
        $slotSelect = Builder::el('select');
        $slotSelect->attrs()->add('name', 'content_slot');
        $slotSelect->attrs()->classAdd('type-select');
        foreach ($availableSlots as $slot) {
            $opt = Builder::el('option');
            $opt->attrs()->add('value', $slot);
            if ($contentSlot === $slot) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content($slot);
            $slotSelect->append($opt);
        }
        $slotGroup->append($slotSelect);
        $settingsBox->append($slotGroup);

        // Скрытие блоков
        if ($availableSlots !== []) {
            $hiddenGroup = Builder::div();
            $hiddenGroup->attrs()->styleAdd('display:flex; flex-direction:column; gap:5px; margin-left: 20px;');
            $hiddenGroup->append(Builder::el('label')->content('Скрыть блоки'));
            $hiddenWrap = Builder::div();
            $hiddenWrap->attrs()->styleAdd('display:flex; gap:15px;');

            foreach ($availableSlots as $slot) {
                $label = Builder::el('label');
                $label->attrs()->styleAdd('display:flex; align-items:center; gap:5px; cursor:pointer;');
                $cb = Builder::el('input', true);
                $cb->attrs()->add('type', 'checkbox');
                $cb->attrs()->add('name', 'hidden_slots[]');
                $cb->attrs()->add('value', $slot);
                if (in_array($slot, $hiddenSlots, true)) {
                    $cb->attrs()->add('checked', 'checked');
                }
                $label->append($cb);
                $label->append(Text::raw(' ' . $slot));
                $hiddenWrap->append($label);
            }
            $hiddenGroup->append($hiddenWrap);
            $settingsBox->append($hiddenGroup);
        }
        $form->append($settingsBox);

        // 2. Контейнер для блоков
        $form->append(Builder::h(3)->content('Контент'));

        $blocksContainer = Builder::div('blocks-container');
        if ($blocks === []) {
            $blocks = [['type' => 'text', 'content' => '']];
        }
        foreach ($blocks as $i => $block) {
            $blocksContainer->append(Text::raw(self::renderBlockRow($i, $block)));
        }
        $form->append($blocksContainer);

        // 3. Кнопки
        $actions = Builder::div();
        $actions->attrs()->styleAdd('margin-top: 15px; display: flex; gap: 10px;');

        $addBtn = Builder::el('button');
        $addBtn->attrs()->add('type', 'button');
        $addBtn->attrs()->add('id', 'add-block-btn');
        $addBtn->attrs()->classAdd('btn btn-outline');
        $addBtn->content(View::t('page.add_block'));
        $actions->append($addBtn);

        $saveBtn = Builder::el('button');
        $saveBtn->attrs()->add('type', 'submit');
        $saveBtn->attrs()->classAdd('btn btn-primary');
        $saveBtn->content(View::t('domain.save'));
        $actions->append($saveBtn);

        $form->append($actions);

        return $form->release();
    }
}
