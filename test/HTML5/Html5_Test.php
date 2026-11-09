<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests;

use Masterminds\HTML5;

class Html5_Test extends Test_Case
{
    /**
     * @var HTML5
     */
    private $html5;

    /**
     * @before
     */
    public function before()
    {
        $this->html5 = $this->getInstance();
    }

    /**
     * Parse and serialize a string.
     */
    protected function cycle($html)
    {
        $dom = $this->html5->load_html('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        $out = $this->html5->save_html($dom);

        return $out;
    }

    protected function cycleFragment($fragment)
    {
        $dom = $this->html5->load_html_fragment($fragment);
        $out = $this->html5->save_html($dom);

        return $out;
    }

    public function testImageTagsInSvg()
    {
        $html = '<!DOCTYPE html>
                    <html>
                        <head>
                            <title>foo</title>
                        </head>
                        <body>
                            <svg>
                                <image height="10" width="10"></image>
                            </svg>
                        </body>
                    </html>';
        $doc = $this->html5->load_html($html);
        $this->assertInstanceOf('DOMElement', $doc->getElementsByTagName('image')->item(0));
        $this->assertEmpty($this->html5->get_errors());
    }

    public function testSelfClosingTableHierarchyElements()
    {
        $html = '
                <table>
                    <thead>
                        <tr>
                            <th>0
                    <tbody>
                        <tr>
                            <td>A
                        <tr>
                            <td>B1
                            <td>B2
                        <tr>
                            <td>C
                    <tfoot>
                        <tr>
                            <th>1
                            <td>2
                    </table>';

        $expected = '<table>
                        <thead>
                            <tr>
                                <th>0</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>A</td>
                            </tr>
                            <tr>
                                <td>B1</td>
                                <td>B2</td>
                            </tr>
                            <tr>
                                <td>C</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>1</th>
                                <td>2</td>
                            </tr>
                        </tfoot>
                    </table>';

        $doc = $this->html5->load_html_fragment($html);
        $this->assertSame(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $this->html5->save_html($doc))
        );
    }

    public function testLoadOptions()
    {
        // doc
        $dom = $this->html5->load_html($this->wrap('<t:tag/>'), [
            'implicit_namespaces' => ['t' => 'http://example.com'],
            'xml_namespaces' => true,
        ]);
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
        $this->assertFalse($this->html5->has_errors());

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('t', 'http://example.com');
        $this->assertEquals(1, $xpath->query('//t:tag')->length);

        // doc fragment
        $frag = $this->html5->load_html_fragment('<t:tag/>', [
            'implicit_namespaces' => ['t' => 'http://example.com'],
            'xml_namespaces' => true,
        ]);
        $this->assertInstanceOf('\DOMDocumentFragment', $frag);
        $this->assertEmpty($this->html5->get_errors());
        $this->assertFalse($this->html5->has_errors());

        $frag->ownerDocument->appendChild($frag);
        $xpath = new \DOMXPath($frag->ownerDocument);
        $xpath->registerNamespace('t', 'http://example.com');
        $this->assertEquals(1, $xpath->query('//t:tag', $frag)->length);
    }

    public function testEncodingUtf8()
    {
        $dom = $this->html5->load(__DIR__ . '/Fixtures/encoding/utf-8.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
        $this->assertFalse($this->html5->has_errors());

        // phpunit 9
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('Žťčýů', $dom->saveHTML());
        } else {
            $this->assertContains('Žťčýů', $dom->saveHTML());
        }
    }

    public function testEncodingWindows1252()
    {
        $dom = $this->html5->load(__DIR__ . '/Fixtures/encoding/windows-1252.html', [
            'encoding' => 'Windows-1252',
        ]);

        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
        $this->assertFalse($this->html5->has_errors());

        $dumpedAsUtf8 = mb_convert_encoding($dom->saveHTML(), 'UTF-8', 'Windows-1252');
        $this->assertNotFalse(mb_strpos($dumpedAsUtf8, 'Ž'));
        $this->assertNotFalse(mb_strpos($dumpedAsUtf8, 'è'));
        $this->assertNotFalse(mb_strpos($dumpedAsUtf8, 'ý'));
        $this->assertNotFalse(mb_strpos($dumpedAsUtf8, 'ù'));
    }

    public function testErrors()
    {
        $dom = $this->html5->load_html('<xx as>');
        $this->assertInstanceOf('\DOMDocument', $dom);

        $this->assertNotEmpty($this->html5->get_errors());
        $this->assertTrue($this->html5->has_errors());
    }

    public function testLoad()
    {
        $dom = $this->html5->load(__DIR__ . '/Html5Test.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
        $this->assertFalse($this->html5->has_errors());

        $file = fopen(__DIR__ . '/Html5Test.html', 'r');
        $dom = $this->html5->load($file);
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());

        $dom = $this->html5->load_html_file(__DIR__ . '/Html5Test.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
    }

    public function testLoadHTML()
    {
        $contents = file_get_contents(__DIR__ . '/Html5Test.html');
        $dom = $this->html5->load_html($contents);
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());
    }

    public function testLoadHTMLWithComments()
    {
        $contents = '<!--[if lte IE 8]> <html class="no-js lt-ie9" lang="en"> <![endif]-->
<!--[if gt IE 8]> <!--><html class="no-js" lang="en"><!--<![endif]-->
</html>';

        $dom = $this->html5->load_html($contents);
        $this->assertInstanceOf('\DOMDocument', $dom);

        $expected = '<!DOCTYPE html>
<!--[if lte IE 8]> <html class="no-js lt-ie9" lang="en"> <![endif]--><!--[if gt IE 8]> <!--><html class="no-js" lang="en"><!--<![endif]--></html>
';
        $this->assertEquals($expected, $this->html5->save_html($dom));
    }

    public function testLoadHTMLFragment()
    {
        $fragment = '<section id="Foo"><div class="Bar">Baz</div></section>';
        $dom = $this->html5->load_html_fragment($fragment);
        $this->assertInstanceOf('\DOMDocumentFragment', $dom);
        $this->assertEmpty($this->html5->get_errors());
    }

    public function testSaveHTML()
    {
        $dom = $this->html5->load(__DIR__ . '/Html5Test.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());

        $saved = $this->html5->save_html($dom);
        $this->assertMatchesRegularExpression('|<p>This is a test.</p>|', $saved);
    }

    public function testSaveHTMLFragment()
    {
        $fragment = '<section id="Foo"><div class="Bar">Baz</div></section>';
        $dom = $this->html5->load_html_fragment($fragment);

        $string = $this->html5->save_html($dom);
        $this->assertEquals($fragment, $string);
    }

    public function testSave()
    {
        $dom = $this->html5->load(__DIR__ . '/Html5Test.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());

        // Test resource
        $file = fopen('php://temp', 'w');
        $this->html5->save($dom, $file);
        $content = stream_get_contents($file, -1, 0);
        $this->assertMatchesRegularExpression('|<p>This is a test.</p>|', $content);

        // Test file
        $tmpfname = tempnam(sys_get_temp_dir(), 'html5-php');
        $this->html5->save($dom, $tmpfname);
        $content = file_get_contents($tmpfname);
        $this->assertMatchesRegularExpression('|<p>This is a test.</p>|', $content);
        unlink($tmpfname);
    }

    // This test reads a document into a dom, turn the dom into a document,
    // then tries to read that document again. This makes sure we are reading,
    // and generating a document that works at a high level.
    public function testItWorks()
    {
        $dom = $this->html5->load(__DIR__ . '/Html5Test.html');
        $this->assertInstanceOf('\DOMDocument', $dom);
        $this->assertEmpty($this->html5->get_errors());

        $saved = $this->html5->save_html($dom);

        $dom2 = $this->html5->load_html($saved);
        $this->assertInstanceOf('\DOMDocument', $dom2);
        $this->assertEmpty($this->html5->get_errors());
    }

    public function testConfig()
    {
        $html5 = $this->getInstance();
        $options = $html5->get_options();
        $this->assertEquals(false, $options['encode_entities']);

        $html5 = $this->getInstance([
            'foo' => 'bar',
            'encode_entities' => true,
        ]);
        $options = $html5->get_options();
        $this->assertEquals('bar', $options['foo']);
        $this->assertEquals(true, $options['encode_entities']);

        // Need to reset to original so future tests pass as expected.
        // $this->getInstance()->setOption('encode_entities', false);
    }

    public function testSvg()
    {
        $dom = $this->html5->load_html(
            '<!doctype html>
      <html lang="en">
        <body>
          <div id="foo" class="bar baz">foo bar baz</div>
          <svg width="150" height="100" viewBox="0 0 3 2">
            <rect width="1" height="2" x="0" fill="#008d46" />
            <rect width="1" height="2" x="1" fill="#ffffff" />
            <rect width="1" height="2" x="2" fill="#d2232c" />
            <text font-family="Verdana" font-size="32">
              <textPath xlink:href="#Foo">
                Test Text.
              </textPath>
            </text>
          </svg>
        </body>
      </html>'
        );

        $this->assertEmpty($this->html5->get_errors());

        // Test a mixed case attribute.
        $list = $dom->getElementsByTagName('svg');
        $this->assertNotEmpty($list->length);
        $svg = $list->item(0);
        $this->assertEquals('0 0 3 2', $svg->getAttribute('viewBox'));
        $this->assertFalse($svg->hasAttribute('viewbox'));

        // Test a mixed case tag.
        // Note: getElementsByTagName is not case sensitive.
        $list = $dom->getElementsByTagName('textPath');
        $this->assertNotEmpty($list->length);
        $textPath = $list->item(0);
        $this->assertEquals('textPath', $textPath->tagName);
        $this->assertNotEquals('textpath', $textPath->tagName);

        $html = $this->html5->save_html($dom);
        $this->assertMatchesRegularExpression('|<svg width="150" height="100" viewBox="0 0 3 2">|', $html);
        $this->assertMatchesRegularExpression('|<rect width="1" height="2" x="0" fill="#008d46" />|', $html);
    }

    public function testMathMl()
    {
        $dom = $this->html5->load_html(
            '<!doctype html>
      <html lang="en">
        <body>
          <div id="foo" class="bar baz" definitionURL="http://example.com">foo bar baz</div>
          <math>
            <mi>x</mi>
            <csymbol definitionURL="http://www.example.com/mathops/multiops.html#plusminus">
              <mo>&PlusMinus;</mo>
            </csymbol>
            <mi>y</mi>
          </math>
        </body>
      </html>'
        );

        $this->assertEmpty($this->html5->get_errors());
        $list = $dom->getElementsByTagName('math');
        $this->assertNotEmpty($list->length);

        $list = $dom->getElementsByTagName('div');
        $this->assertNotEmpty($list->length);
        $div = $list->item(0);
        $this->assertEquals('http://example.com', $div->getAttribute('definitionurl'));
        $this->assertFalse($div->hasAttribute('definitionURL'));
        $list = $dom->getElementsByTagName('csymbol');
        $csymbol = $list->item(0);
        $this->assertEquals('http://www.example.com/mathops/multiops.html#plusminus', $csymbol->getAttribute('definitionURL'));
        $this->assertFalse($csymbol->hasAttribute('definitionurl'));

        $html = $this->html5->save_html($dom);
        $this->assertMatchesRegularExpression('|<csymbol definitionURL="http://www.example.com/mathops/multiops.html#plusminus">|', $html);
        $this->assertMatchesRegularExpression('|<mi>y</mi>|', $html);
    }

    public function testUnknownElements()
    {
        // The : should not have special handling accourding to section 2.9 of the
        // spec. This is differenant than XML. Since we don't know these elements
        // they are handled as normal elements. Note, to do this is really
        // an invalid example and you should not embed prefixed xml in html5.
        $dom = $this->html5->load_html_fragment(
            '<f:rug>
      <f:name>Big rectangle thing</f:name>
      <f:width>40</f:width>
      <f:length>80</f:length>
    </f:rug>
    <sarcasm>um, yeah</sarcasm>'
        );

        $this->assertEmpty($this->html5->get_errors());
        $markup = $this->html5->save_html($dom);
        $this->assertMatchesRegularExpression('|<f:name>Big rectangle thing</f:name>|', $markup);
        $this->assertMatchesRegularExpression('|<sarcasm>um, yeah</sarcasm>|', $markup);
    }

    public function testElements()
    {
        // Should have content.
        $res = $this->cycle('<div>FOO</div>');
        $this->assertMatchesRegularExpression('|<div>FOO</div>|', $res);

        // Should be empty
        $res = $this->cycle('<span></span>');
        $this->assertMatchesRegularExpression('|<span></span>|', $res);

        // Should have content.
        $res = $this->cycleFragment('<div>FOO</div>');
        $this->assertMatchesRegularExpression('|<div>FOO</div>|', $res);

        // Should be empty
        $res = $this->cycleFragment('<span></span>');
        $this->assertMatchesRegularExpression('|<span></span>|', $res);

        // Elements with dashes and underscores
        $res = $this->cycleFragment('<sp-an></sp-an>');
        $this->assertMatchesRegularExpression('|<sp-an></sp-an>|', $res);
        $res = $this->cycleFragment('<sp_an></sp_an>');
        $this->assertMatchesRegularExpression('|<sp_an></sp_an>|', $res);

        // Should have no closing tag.
        $res = $this->cycle('<hr>');
        $this->assertMatchesRegularExpression('|<hr></body>|', $res);
    }

    public function testAttributes()
    {
        $res = $this->cycle('<use xlink:href="#svg-track" xmlns:xlink="http://www.w3.org/1999/xlink"></use>');

        // phpunit 9
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('<use xlink:href="#svg-track" xmlns:xlink="http://www.w3.org/1999/xlink"></use>', $res);
        } else {
            $this->assertContains('<use xlink:href="#svg-track" xmlns:xlink="http://www.w3.org/1999/xlink"></use>', $res);
        }

        $res = $this->cycle('<div attr="val">FOO</div>');
        $this->assertMatchesRegularExpression('|<div attr="val">FOO</div>|', $res);

        // XXX: Note that spec does NOT require attrs in the same order.
        $res = $this->cycle('<div attr="val" class="even">FOO</div>');
        $this->assertMatchesRegularExpression('|<div attr="val" class="even">FOO</div>|', $res);

        $res = $this->cycle('<div xmlns:foo="http://example.com">FOO</div>');
        $this->assertMatchesRegularExpression('|<div xmlns:foo="http://example.com">FOO</div>|', $res);

        $res = $this->cycleFragment('<div attr="val">FOO</div>');
        $this->assertMatchesRegularExpression('|<div attr="val">FOO</div>|', $res);

        // XXX: Note that spec does NOT require attrs in the same order.
        $res = $this->cycleFragment('<div attr="val" class="even">FOO</div>');
        $this->assertMatchesRegularExpression('|<div attr="val" class="even">FOO</div>|', $res);

        $res = $this->cycleFragment('<div xmlns:foo="http://example.com">FOO</div>');
        $this->assertMatchesRegularExpression('|<div xmlns:foo="http://example.com">FOO</div>|', $res);
    }

    public function testPCData()
    {
        $res = $this->cycle('<a>This is a test.</a>');
        $this->assertMatchesRegularExpression('|This is a test.|', $res);

        $res = $this->cycleFragment('<a>This is a test.</a>');
        $this->assertMatchesRegularExpression('|This is a test.|', $res);

        $res = $this->cycle('This
      is
      a
      test.');

        // Check that newlines are there, but don't count spaces.
        $this->assertMatchesRegularExpression('|This\n\s*is\n\s*a\n\s*test.|', $res);

        $res = $this->cycleFragment('This
      is
      a
      test.');

        // Check that newlines are there, but don't count spaces.
        $this->assertMatchesRegularExpression('|This\n\s*is\n\s*a\n\s*test.|', $res);

        $res = $this->cycle('<a>This <em>is</em> a test.</a>');
        $this->assertMatchesRegularExpression('|This <em>is</em> a test.|', $res);

        $res = $this->cycleFragment('<a>This <em>is</em> a test.</a>');
        $this->assertMatchesRegularExpression('|This <em>is</em> a test.|', $res);
    }

    public function testUnescaped()
    {
        $res = $this->cycle('<script>2 < 1</script>');
        $this->assertMatchesRegularExpression('|2 < 1|', $res);

        $res = $this->cycle('<style>div>div>div</style>');
        $this->assertMatchesRegularExpression('|div>div>div|', $res);

        $res = $this->cycleFragment('<script>2 < 1</script>');
        $this->assertMatchesRegularExpression('|2 < 1|', $res);

        $res = $this->cycleFragment('<style>div>div>div</style>');
        $this->assertMatchesRegularExpression('|div>div>div|', $res);
    }

    public function testEntities()
    {
        $res = $this->cycle('<a>Apples &amp; bananas.</a>');
        $this->assertMatchesRegularExpression('|Apples &amp; bananas.|', $res);

        $res = $this->cycleFragment('<a>Apples &amp; bananas.</a>');
        $this->assertMatchesRegularExpression('|Apples &amp; bananas.|', $res);

        $res = $this->cycleFragment('<p>R&D</p>');
        $this->assertMatchesRegularExpression('|R&amp;D|', $res);
    }

    public function testCaseSensitiveTags()
    {
        $dom = $this->html5->load_html(
            '<html><body><Button color="red">Error</Button></body></html>',
            [
                'xml_namespaces' => true,
            ]
        );
        $out = $this->html5->save_html($dom);
        $this->assertMatchesRegularExpression('|<html><body><Button color="red">Error</Button></body></html>|', $out);
    }

    public function testComment()
    {
        $res = $this->cycle('a<!-- This is a test. -->b');
        $this->assertMatchesRegularExpression('|<!-- This is a test. -->|', $res);

        $res = $this->cycleFragment('a<!-- This is a test. -->b');
        $this->assertMatchesRegularExpression('|<!-- This is a test. -->|', $res);
    }

    public function testCDATA()
    {
        $res = $this->cycle('a<![CDATA[ This <is> a test. ]]>b');
        $this->assertMatchesRegularExpression('|<!\[CDATA\[ This <is> a test\. \]\]>|', $res);

        $res = $this->cycleFragment('a<![CDATA[ This <is> a test. ]]>b');
        $this->assertMatchesRegularExpression('|<!\[CDATA\[ This <is> a test\. \]\]>|', $res);
    }

    public function testAnchorTargetQueryParam()
    {
        $res = $this->cycle('<a href="https://domain.com/page.php?foo=bar&target=baz">https://domain.com/page.php?foo=bar&target=baz</a>');

        // phpunit 9
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('<a href="https://domain.com/page.php?foo=bar&amp;target=baz">https://domain.com/page.php?foo=bar&amp;target=baz</a>', $res);
        } else {
            $this->assertContains('<a href="https://domain.com/page.php?foo=bar&amp;target=baz">https://domain.com/page.php?foo=bar&amp;target=baz</a>', $res);
        }
    }
}
