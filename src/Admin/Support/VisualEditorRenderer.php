<?php

declare(strict_types=1);

namespace Damoony\Admin\Support;

use Damoony\Admin\View;
use Damoony\Ui\Builder;
use Damoony\Ui\Element;
use Damoony\Ui\Text;

final class VisualEditorRenderer
{
    /**
     * @param array<string, mixed> $block
     */
    public static function renderBlockRow(int $index, array $block, string $prefix = 'blocks'): Element
    {
        $type = (string)($block['type'] ?? 'text');
        $content = (string)($block['content'] ?? '');

        $tile = Builder::div('pb-block-tile editor-block-row');
        $tile->attrs()->add('data-type', $type);

        // Заголовок плитки
        $header = Builder::div('pb-block-header');
        $header->append(Builder::span('pb-block-title')->content(strtoupper($type)));

        $removeBtn = Builder::button('button', 'remove-block-btn');
        $removeBtn->content('×');
        $header->append($removeBtn);
        $tile->append($header);

        // Скрытое поле типа
        $hiddenType = Builder::input('hidden', "{$prefix}[{$index}][type]");
        $hiddenType->attrs()->add('value', $type);
        $tile->append($hiddenType);

        // Поле контента
        if ($type === 'img') {
            $input = Builder::input('text', "{$prefix}[{$index}][content]");
            $input->attrs()->add('value', $content);
            $input->attrs()->add('placeholder', View::t('admin.page.type_img'));
            $input->attrs()->classAdd('pb-input');
            $tile->append($input);
        } else {
            $textarea = Builder::el('textarea');
            $textarea->attrs()->add('name', "{$prefix}[{$index}][content]");
            $textarea->attrs()->add('placeholder', View::t('admin.page.block_content'));
            $textarea->attrs()->add('rows', '3');
            $textarea->attrs()->classAdd('pb-textarea');
            $textarea->content($content);
            $tile->append($textarea);
        }

        return $tile;
    }

    /**
     * @param array{id?:int, parent?:?int, tag?:string, class?:string, layout?:string, slot?:string} $item
     * @param list<string> $availableSlots
     * @param list<array{id:int, label:string}> $containers
     */
    public static function renderSlotRow(int $index, array $item, array $availableSlots, array $containers = []): Element
    {
        $rowId = (int)($item['id'] ?? $index);
        $slotName = (string)($item['slot'] ?? '');
        $tag = (string)($item['tag'] ?? 'div');
        $class = (string)($item['class'] ?? '');
        $layout = (string)($item['layout'] ?? '');
        $parentId = $item['parent'] ?? null;

        $row = Builder::div('editor-row');

        $moveDiv = Builder::div('move-buttons');
        $upBtn = Builder::el('button');
        $upBtn->attrs()->classAdd('btn btn-outline move-up-btn');
        $upBtn->attrs()->add('type', 'button');
        $upBtn->content('▲');
        $moveDiv->append($upBtn);
        $downBtn = Builder::el('button');
        $downBtn->attrs()->classAdd('btn btn-outline move-down-btn');
        $downBtn->attrs()->add('type', 'button');
        $downBtn->content('▼');
        $moveDiv->append($downBtn);
        $row->append($moveDiv);

        $inputId = Builder::el('input', true);
        $inputId->attrs()->add('type', 'hidden');
        $inputId->attrs()->add('name', "slots[{$index}][id]");
        $inputId->attrs()->add('value', (string)$rowId);
        $row->append($inputId);

        $selectParent = Builder::el('select');
        $selectParent->attrs()->classAdd('parent-select');
        $selectParent->attrs()->add('name', "slots[{$index}][parent]");

        $optRoot = Builder::el('option');
        $optRoot->attrs()->add('value', '');
        if ($parentId === null) {
            $optRoot->attrs()->add('selected', 'selected');
        }
        $optRoot->content(View::t('admin.editor.root'));
        $selectParent->append($optRoot);

        foreach ($containers as $c) {
            if ($c['id'] === $rowId) {
                continue;
            }
            $opt = Builder::el('option');
            $opt->attrs()->add('value', (string)$c['id']);
            if ($parentId === $c['id']) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content('в #' . $c['id'] . ' ' . $c['label']);
            $selectParent->append($opt);
        }
        $row->append($selectParent);

        $selectSlot = Builder::el('select');
        $selectSlot->attrs()->classAdd('type-select');
        $selectSlot->attrs()->add('name', "slots[{$index}][name]");

        $optNone = Builder::el('option');
        $optNone->attrs()->add('value', '');
        if ($slotName === '') {
            $optNone->attrs()->add('selected', 'selected');
        }
        $optNone->content(View::t('admin.editor.container_no_slot'));
        $selectSlot->append($optNone);

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

        $selectTag = Builder::el('select');
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

        $inputClass = Builder::el('input', true);
        $inputClass->attrs()->classAdd('content-input');
        $inputClass->attrs()->add('type', 'text');
        $inputClass->attrs()->add('name', "slots[{$index}][class]");
        $inputClass->attrs()->add('value', $class);
        $inputClass->attrs()->add('placeholder', View::t('admin.editor.css_class'));
        $row->append($inputClass);

        $selectLayout = Builder::el('select');
        $selectLayout->attrs()->classAdd('layout-select');
        $selectLayout->attrs()->add('name', "slots[{$index}][layout]");

        $optNone = Builder::el('option');
        $optNone->attrs()->add('value', '');
        if ($layout === '') {
            $optNone->attrs()->add('selected', 'selected');
        }
        $optNone->content('layout: —');
        $selectLayout->append($optNone);

        foreach (['vertical', 'horizontal'] as $l) {
            $opt = Builder::el('option');
            $opt->attrs()->add('value', $l);
            if ($layout === $l) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content('layout: ' . $l);
            $selectLayout->append($opt);
        }
        $row->append($selectLayout);

        $btn = Builder::el('button');
        $btn->attrs()->classAdd('btn btn-danger remove-slot-btn remove-btn');
        $btn->attrs()->add('type', 'button');
        $btn->content('✕');
        $row->append($btn);

        return $row;
    }

    /**
     * @param array<string, array{status: string, blocks: list<array{type: string, content: string}>}> $pageSlots
     * @param list<string> $availableSlots
     */
    public static function renderPageSlotsForm(string $action, string $csrfField, array $pageSlots, array $availableSlots): Element
    {
        $form = Builder::el('form');
        $form->attrs()->add('method', 'post');
        $form->attrs()->add('action', $action);
        $form->attrs()->classAdd('editor-form');
        $form->append(Text::raw($csrfField));

        foreach ($availableSlots as $slotName) {
            $config = $pageSlots[$slotName] ?? ['status' => 'global', 'blocks' => []];
            $status = $config['status'];
            $blocks = $config['blocks'];

            $card = Builder::div('editor-card');
            $card->attrs()->styleAdd('border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; background: #fff; overflow: hidden;');

            $header = Builder::div();
            $header->attrs()->styleAdd('display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f9fafb; border-bottom: 1px solid #eee;');

            $title = Builder::h(3)->content(View::t('admin.editor.block') . ': ' . strtoupper($slotName));
            $title->attrs()->styleAdd('margin: 0; font-size: 16px;');
            $header->append($title);

            $statusSelect = Builder::el('select');
            $statusSelect->attrs()->add('name', "slots[{$slotName}][status]");
            $statusSelect->attrs()->classAdd('slot-status-select');
            $statusSelect->attrs()->styleAdd('padding: 6px; border-radius: 6px; border: 1px solid #ccc;');

            $statuses = [
                'disabled' => View::t('admin.editor.status_disabled'),
                'global' => View::t('admin.editor.status_global'),
                'local' => View::t('admin.editor.status_local'),
            ];
            foreach ($statuses as $val => $label) {
                $opt = Builder::el('option');
                $opt->attrs()->add('value', $val);
                if ($status === $val) {
                    $opt->attrs()->add('selected', 'selected');
                }
                $opt->content($label);
                $statusSelect->append($opt);
            }
            $header->append($statusSelect);
            $card->append($header);

            $body = Builder::div('slot-body');
            $body->attrs()->styleAdd('padding: 15px; display: ' . ($status === 'local' ? 'block' : 'none') . ';');

            $body->append(Builder::h(4)->content(View::t('admin.editor.visual_data')));

            $container = Builder::div('blocks-container');
            if ($blocks === []) {
                $blocks = [['type' => 'text', 'content' => '']];
            }

            $prefix = "slots[{$slotName}][blocks]";
            foreach ($blocks as $i => $block) {
                $container->append(self::renderBlockRow($i, $block, $prefix));
            }
            $body->append($container);

            $addBtn = Builder::el('button');
            $addBtn->attrs()->add('type', 'button');
            $addBtn->attrs()->classAdd('btn btn-outline add-slot-block-btn');
            $addBtn->attrs()->styleAdd('margin-top: 10px;');
            $addBtn->attrs()->add('data-prefix', $prefix);
            $addBtn->content(View::t('admin.page.add_block'));
            $body->append($addBtn);

            $card->append($body);
            $form->append($card);
        }

        $actions = Builder::div();
        $actions->attrs()->styleAdd('margin-top: 20px;');
        $saveBtn = Builder::el('button');
        $saveBtn->attrs()->add('type', 'submit');
        $saveBtn->attrs()->classAdd('btn btn-primary');
        $saveBtn->content(View::t('domain.save'));
        $actions->append($saveBtn);
        $form->append($actions);

        return $form;
    }
}
