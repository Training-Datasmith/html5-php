<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests\Serializer;

use Masterminds\HTML5\Serializer\Output_Rules;
use Masterminds\HTML5\Serializer\Traverser;

class Traverser_Test extends \Masterminds\HTML5\Tests\Test_Case
{
    protected $markup = '<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <title>Test</title>
      </head>
      <body>
        <p>This is a test.</p>
      </body>
    </html>';

    /**
     * @before
     */
    public function before()
    {
        $this->html5 = $this->getInstance();
    }

    /**
     * Using reflection we make a protected method accessible for testing.
     *
     * @param string $name
     *                     The name of the method on the Traverser class to test
     *
     * @return \ReflectionMethod \ReflectionMethod for the specified method
     */
    public function getProtectedMethod($name)
    {
        $class = new \ReflectionClass('\Masterminds\HTML5\Serializer\Traverser');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function getTraverser()
    {
        $stream = fopen('php://temp', 'w');

        $dom = $this->html5->load_html($this->markup);
        $t = new Traverser($dom, $stream, $this->html5->get_options());

        // We return both the traverser and stream so we can pull from it.
        return [
            $t,
            $stream,
        ];
    }

    public function testConstruct()
    {
        // The traverser needs a place to write the output to. In our case we
        // use a stream in temp space.
        $stream = fopen('php://temp', 'w');

        $html5 = $this->getInstance();

        $r = new Output_Rules($stream, $this->html5->get_options());
        $dom = $this->html5->load_html($this->markup);

        $t = new Traverser($dom, $stream, $r, $html5->get_options());

        $this->assertInstanceOf('\Masterminds\HTML5\Serializer\Traverser', $t);
    }

    public function testFragmentDeprecated()
    {
        $html = '<span class="bar">foo</span><span></span><div>bar</div>';
        $input = new \Masterminds\HTML5\Parser\String_Input_Stream($html);
        $dom = $this->html5->parse_fragment($input);

        $this->assertInstanceOf('\DOMDocumentFragment', $dom);

        $stream = fopen('php://temp', 'w');
        $r = new Output_Rules($stream, $this->html5->get_options());
        $t = new Traverser($dom, $stream, $r, $this->html5->get_options());
        $t->walk();

        $this->assertEquals($html, stream_get_contents($stream, -1, 0));
    }

    public function testFragment()
    {
        $html = '<span class="bar">foo</span><span></span><div>bar</div>';
        $dom = $this->html5->parse_fragment($html);

        $this->assertInstanceOf('\DOMDocumentFragment', $dom);

        $stream = fopen('php://temp', 'w');
        $r = new Output_Rules($stream, $this->html5->get_options());
        $t = new Traverser($dom, $stream, $r, $this->html5->get_options());
        $t->walk();

        $this->assertEquals($html, stream_get_contents($stream, -1, 0));
    }

    public function testProcessorInstructionDeprecated()
    {
        $html = '<?foo bar ?>';
        $input = new \Masterminds\HTML5\Parser\String_Input_Stream($html);
        $dom = $this->html5->parse_fragment($input);

        $this->assertInstanceOf('\DOMDocumentFragment', $dom);

        $stream = fopen('php://temp', 'w');
        $r = new Output_Rules($stream, $this->html5->get_options());

        $t = new Traverser($dom, $stream, $r, $this->html5->get_options());
        $t->walk();

        $this->assertEquals($html, stream_get_contents($stream, -1, 0));
    }

    public function testProcessorInstruction()
    {
        $html = '<?foo bar ?>';
        $dom = $this->html5->parse_fragment($html);

        $this->assertInstanceOf('\DOMDocumentFragment', $dom);

        $stream = fopen('php://temp', 'w');
        $r = new Output_Rules($stream, $this->html5->get_options());

        $t = new Traverser($dom, $stream, $r, $this->html5->get_options());
        $t->walk();

        $this->assertEquals($html, stream_get_contents($stream, -1, 0));
    }
}
