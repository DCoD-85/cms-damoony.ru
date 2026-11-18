<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\UI;

use Damoony\UI\Components\Button;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Td;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AppendRulesTest extends TestCase
{
    public function testValidTableStructure(): void
    {
        $row = new Tr();
        $row->append(new Td('Анна'));
        $row->append(new Td('admin'));
        $row->append(new Td(new Button('Изменить', 'button')));

        $tbody = new Tbody();
        $tbody->append($row);

        $table = new Table();
        $table->append($tbody);

        $html = $table->release();

        self::assertStringContainsString('<table class="ui-table">', $html);
        self::assertStringContainsString('<tbody class="ui-tbody">', $html);
        self::assertStringContainsString('<tr class="ui-tr">', $html);
        self::assertStringContainsString('<td class="ui-td">Анна</td>', $html);
        self::assertStringContainsString('<button class="ui-btn" type="button">Изменить</button>', $html);
    }

    public function testTableAllowsTheadAndTr(): void
    {
        $table = new Table();
        $table->append(new Thead());
        $table->append(new Tr());

        self::assertStringContainsString('<thead class="ui-thead">', $table->release());
    }

    public function testTdRejectsStructuralElements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('нельзя вставлять');

        new Td()->append(new Td('x'));
    }

    public function testTdRejectsTable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Td()->append(new Table());
    }

    public function testTrRejectsTable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Tr()->append(new Table());
    }

    public function testTbodyRejectsTd(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Tbody()->append(new Td('x'));
    }

    public function testTableRejectsText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Table()->content('text');
    }

    public function testTableRejectsTdDirectly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Table()->append(new Td('x'));
    }

    public function testThIsAllowedInTr(): void
    {
        $tr = new Tr();
        $tr->append(new Th('Заголовок'));

        self::assertStringContainsString('<th class="ui-th">Заголовок</th>', $tr->release());
    }

    public function testInputIsVoid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Input('text', 'name')->append('text');
    }

    public function testButtonOnlyAcceptsText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Button('ok')->append(new Td('x'));
    }

    public function testSelectAcceptsOnlyOptions(): void
    {
        $select = new Select('city');
        $select->add(new Option('msk', 'Москва'));

        self::assertStringContainsString('<select', $select->release());

        $this->expectException(InvalidArgumentException::class);
        $select->add(new Td('x'));
    }
}
