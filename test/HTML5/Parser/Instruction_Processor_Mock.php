<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests\Parser;

class Instruction_Processor_Mock implements \Masterminds\HTML5\Instruction_Processor
{
    public $name = null;

    public $data = null;

    public $count = 0;

    public function process(\DOMElement $element, $name, $data)
    {
        $this->name = $name;
        $this->data = $data;
        ++$this->count;

        $div = $element->ownerDocument->createElement('div');
        $div->nodeValue = 'foo';

        $element->appendChild($div);

        return $div;
    }
}
