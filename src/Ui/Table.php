<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Table extends Component
{
    /** @var array<string> */
    private array $headers = [];
    /** @var list<list<Node|string>> */
    private array $rows = [];

    public function headers(string ...$headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /** @param list<Node|string> $cells */
    public function row(array $cells): self
    {
        $this->rows[] = $cells;
        return $this;
    }

    public function build(): Node
    {
        $table = new Element('table');
        $table->attrs()->classAdd('table');

        if ($this->headers !== []) {
            $thead = new Element('thead');
            $tr = new Element('tr');
            foreach ($this->headers as $h) {
                $th = new Element('th');
                $th->content($h);
                $tr->append($th);
            }
            $thead->append($tr);
            $table->append($thead);
        }

        $tbody = new Element('tbody');
        foreach ($this->rows as $row) {
            $tr = new Element('tr');
            foreach ($row as $cell) {
                $td = new Element('td');
                $td->append($cell);
                $tr->append($td);
            }
            $tbody->append($tr);
        }
        $table->append($tbody);

        return $table;
    }
}