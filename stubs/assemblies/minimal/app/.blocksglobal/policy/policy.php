<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;

final class blocksglobal_policy implements RegionInterface
{
    public function name(): string { return 'policy'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;

        // Сервер: если пользователь уже принял — banner скрыт (no-JS работает).
        if (($_COOKIE['damoony_policy'] ?? '') === 'accepted') {
            $hidden = Builder::div('cookie-banner');
            $hidden->attrs()->add('id', 'cookie-banner');
            $hidden->attrs()->classAdd('is-hidden');
            return $hidden;
        }

        $wrap = Builder::div('cookie-banner');
        $wrap->attrs()->add('id', 'cookie-banner');
        // Без inline display:none — no-JS: мини-окно видно сразу;
        // JS (M6c) добавит .enhanced (модалка по размеру окна).

        $text = Builder::div('cookie-text');
        $rawText = strip_tags(
            $context->lang('block.policy.text', ['url' => '/' . $lang . '/policy/']),
            '<a><strong>'
        );
        $text->append(Builder::p()->append(Text::raw($rawText)));
        $wrap->append($text);

        $buttons = Builder::div('cookie-buttons');

        // No-JS fallback: <form method=POST action=/{lang}/policy/accept> → cookie + 302.
        // JS (M6c): перехватит submit → fetch POST на /{lang}/policy/ajax.
        $form = Builder::el('form');
        $form->attrs()->add('method', 'POST');
        $form->attrs()->add('action', '/' . $lang . '/policy/accept');
        $form->attrs()->classAdd('cookie-accept-form');
        $csrf = $context->service('csrf');
        if ($csrf instanceof \Damoony\Core\Security\Csrf) {
            $form->append(Builder::input('hidden', '_csrf', $csrf->token()));
        }
        $accept = Builder::button('submit', 'btn-accept');
        $accept->content($context->lang('block.policy.accept'));
        $form->append($accept);
        $buttons->append($form);

        // Close (JS-only): скрывает banner, БЕЗ cookie. Reload — сервер покажет снова.
        $decline = Builder::button('button', 'btn-decline');
        $decline->attrs()->add('data-action', 'close');
        $decline->content($context->lang('block.policy.decline'));
        $buttons->append($decline);

        $wrap->append($buttons);
        return $wrap;
    }
}
