<?php
declare(strict_types=1);
namespace damoony_ru;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_policy implements RegionInterface
{
    public function name(): string { return 'policy'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('cookie-banner');
        $wrap->attrs()->add('id', 'cookie-banner');
        $wrap->attrs()->add('style', 'display: none;'); // По умолчанию скрыт, покажет JS

        $text = Builder::div('cookie-text');
        $text->append(Builder::p()->content(
            $context->lang('block.policy.text', ['url' => '/' . $context->domain->language . '/policy/'])
        ));
        $wrap->append($text);

        $buttons = Builder::div('cookie-buttons');
        
        $accept = Builder::button('button', 'btn-accept');
        $accept->content($context->lang('block.policy.accept'));
        $buttons->append($accept);

        $decline = Builder::button('button', 'btn-decline');
        $decline->content($context->lang('block.policy.decline'));
        $buttons->append($decline);

        $wrap->append($buttons);
        return $wrap;
    }
}
