<?php

declare(strict_types=1);
/**
 * @file
 * Test the Scanner. This requires the String_Input_Stream tests are all good.
 */

namespace Masterminds\HTML5\Tests\Parser;

use Masterminds\HTML5\Parser\Scanner;
use Masterminds\HTML5\Parser\String_Input_Stream;

class Scanner_Test extends \Masterminds\HTML5\Tests\Test_Case
{
    /**
     * A canary test to make sure the basics are setup and working.
     */
    public function testConstructDeprecated()
    {
        $is = new String_Input_Stream('abc');
        $s = new Scanner($is);

        $this->assertInstanceOf('\Masterminds\HTML5\Parser\Scanner', $s);
    }

    public function testConstruct()
    {
        $this->assertInstanceOf('\Masterminds\HTML5\Parser\Scanner', new Scanner('abc'));
    }

    public function testNextDeprecated()
    {
        $s = new Scanner(new String_Input_Stream('abc'));

        $this->assertEquals('b', $s->next());
        $this->assertEquals('c', $s->next());
    }

    public function testNext()
    {
        $s = new Scanner('abc');

        $this->assertEquals('b', $s->next());
        $this->assertEquals('c', $s->next());
    }

    public function testPosition()
    {
        $s = new Scanner('abc');

        $this->assertEquals(0, $s->position());

        $s->next();
        $this->assertEquals(1, $s->position());
    }

    public function testPeek()
    {
        $s = new Scanner('abc');

        $this->assertEquals('b', $s->peek());

        $s->next();
        $this->assertEquals('c', $s->peek());
    }

    public function testCurrent()
    {
        $s = new Scanner('abc');

        // Before scanning the string begins the current is empty.
        $this->assertEquals('a', $s->current());

        $c = $s->next();
        $this->assertEquals('b', $s->current());

        // Test movement through the string.
        $c = $s->next();
        $this->assertEquals('c', $s->current());
    }

    public function testUnconsume()
    {
        $s = new Scanner('abcdefghijklmnopqrst');

        // Get initial position.
        $s->next();
        $start = $s->position();

        // Move forward a bunch of positions.
        $amount = 7;
        for ($i = 0; $i < $amount; ++$i) {
            $s->next();
        }

        // Roll back the amount we moved forward.
        $s->unconsume($amount);

        $this->assertEquals($start, $s->position());
    }

    public function testGetHex()
    {
        $s = new Scanner('ab13ck45DE*');

        $this->assertEquals('ab13c', $s->get_hex());

        $s->next();
        $this->assertEquals('45DE', $s->get_hex());
    }

    public function testGetAsciiAlpha()
    {
        $s = new Scanner('abcdef1%mnop*');

        $this->assertEquals('abcdef', $s->get_ascii_alpha());

        // Move past the 1% to scan the next group of text.
        $s->next();
        $s->next();
        $this->assertEquals('mnop', $s->get_ascii_alpha());
    }

    public function testGetAsciiAlphaNum()
    {
        $s = new Scanner('abcdef1ghpo#mn94op');

        $this->assertEquals('abcdef1ghpo', $s->get_ascii_alpha_num());

        // Move past the # to scan the next group of text.
        $s->next();
        $this->assertEquals('mn94op', $s->get_ascii_alpha_num());
    }

    public function testGetNumeric()
    {
        $s = new Scanner('1784a 45 9867 #');

        $this->assertEquals('1784', $s->get_numeric());

        // Move past the 'a ' to scan the next group of text.
        $s->next();
        $s->next();
        $this->assertEquals('45', $s->get_numeric());
    }

    public function testCurrentLine()
    {
        $s = new Scanner("1784a\n45\n9867 #\nThis is a test.");

        $this->assertEquals(1, $s->current_line());

        // Move to the next line.
        $s->get_ascii_alpha_num();
        $s->next();
        $this->assertEquals(2, $s->current_line());
    }

    public function testColumnOffset()
    {
        $s = new Scanner("1784a a\n45 9867 #\nThis is a test.");

        // Move the pointer to the space.
        $s->get_ascii_alpha_num();
        $this->assertEquals(5, $s->column_offset());

        // We move the pointer ahead. There must be a better way to do this.
        $s->next();
        $s->next();
        $s->next();
        $s->next();
        $s->next();
        $s->next();
        $this->assertEquals(3, $s->column_offset());
    }

    public function testRemainingChars()
    {
        $string = "\n45\n9867 #\nThis is a test.";
        $s = new Scanner("1784a\n45\n9867 #\nThis is a test.");

        $s->get_ascii_alpha_num();
        $this->assertEquals($string, $s->remaining_chars());
    }
}
