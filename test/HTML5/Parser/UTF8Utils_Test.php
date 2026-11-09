<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests\Parser;

use Masterminds\HTML5\Parser\UTF8Utils;

class UTF8Utils_Test extends \Masterminds\HTML5\Tests\Test_Case
{
    public function testConvertToUTF8()
    {
        $out = UTF8Utils::convert_to_utf8('éàa', 'ISO-8859-1');
        $this->assertEquals('Ã©Ã a', $out);
    }

    /**
     * @todo add tests for invalid codepoints
     */
    public function testCheckForIllegalCodepoints()
    {
        $smoke = 'Smoke test';
        $err = UTF8Utils::check_for_illegal_codepoints($smoke);
        $this->assertEmpty($err);

        $data = "Foo Bar \0 Baz";
        $errors = UTF8Utils::check_for_illegal_codepoints($data);
        $this->assertContains('null-character', $errors);
    }
}
