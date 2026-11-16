<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Img;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Textarea;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Td;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;
use Damoony\Core\Http\Response;

final class algoritm_lang_policy extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        $content = Builder::div('page-content');
        $content->append(Builder::h(1)->content($this->context->lang('policy.title')));

        foreach (['intro', 'cookies', 'data', 'rights'] as $section) {
            $content->append(Builder::h(2)->content($this->context->lang('policy.' . $section . '.title')));
            
            $rawText = strip_tags(
                $this->context->lang('policy.' . $section . '.text'),
                '<a><strong>'
            );
            $content->append(Builder::p()->append(Text::raw($rawText)));
        }

        // No-JS fallback: форма «Принять» (POST → /{lang}/policy/accept → cookie → 302).
        $lang = $this->context->domain->language;
        $form = new Form('policy-accept-form');
        $form->action('/' . $lang . '/policy/accept');
        $form->method('POST');
        $csrf = $this->context->service('csrf');
        if ($csrf instanceof \Damoony\Core\Security\Csrf) {
            $form->csrf($csrf);
        }
        $form->add(new Button($this->context->lang('policy.accept'), 'submit'));
        $content->append($form);

        return $content;
    }
}
