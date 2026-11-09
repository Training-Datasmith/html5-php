<?php

declare(strict_types=1);
/**
 * @file
 * Test the Scanner. This requires the String_Input_Stream tests are all good.
 */

namespace Masterminds\HTML5\Tests\Parser;

use Masterminds\HTML5\Parser\Character_Reference;

class Character_Reference_Test extends \Masterminds\HTML5\Tests\Test_Case
{
    public function testLookupName()
    {
        $this->assertEquals('&', Character_Reference::lookup_name('amp'));
        $this->assertEquals('<', Character_Reference::lookup_name('lt'));
        $this->assertEquals('>', Character_Reference::lookup_name('gt'));
        $this->assertEquals('"', Character_Reference::lookup_name('quot'));
        $this->assertEquals('∌', Character_Reference::lookup_name('NotReverseElement'));

        $this->assertNull(Character_Reference::lookup_name('StinkyCheese'));
    }

    public function testLookupHex()
    {
        $this->assertEquals('<', Character_Reference::lookup_hex('3c'));
        $this->assertEquals('<', Character_Reference::lookup_hex('003c'));
        $this->assertEquals('&', Character_Reference::lookup_hex('26'));
        $this->assertEquals('}', Character_Reference::lookup_hex('7d'));
        $this->assertEquals('Σ', Character_Reference::lookup_hex('3A3'));
        $this->assertEquals('Σ', Character_Reference::lookup_hex('03A3'));
        $this->assertEquals('Σ', Character_Reference::lookup_hex('3a3'));
        $this->assertEquals('Σ', Character_Reference::lookup_hex('03a3'));
    }

    public function testLookupDecimal()
    {
        $this->assertEquals('&', Character_Reference::lookup_decimal(38));
        $this->assertEquals('&', Character_Reference::lookup_decimal('38'));
        $this->assertEquals('<', Character_Reference::lookup_decimal(60));
        $this->assertEquals('Σ', Character_Reference::lookup_decimal(931));
        $this->assertEquals('Σ', Character_Reference::lookup_decimal('0931'));
    }
}
