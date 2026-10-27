<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Message extends Component
{
    private string $text = '';
    private string $type = 'info';

    public static function info(string $text): self
    {
        return new self($text, 'info');
    }

    public static function success(string $text): self
    {
        return new self($text, 'success');
    }

    public static function warning(string $text): self
    {
        return new self($text, 'warning');
    }

    public static function error(string $text): self
    {
        return new self($text, 'error');
    }

    public function __construct(string $text, string $type = 'info')
    {
        $this->text = $text;
        $this->type = $type;
    }

    public function build(): Node
    {
        $msg = Builder::div('message message-' . $this->type);
        $msg->content($this->text);
        return $msg;
    }
}
