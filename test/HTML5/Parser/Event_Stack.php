<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests\Parser;

use Masterminds\HTML5\Elements;
use Masterminds\HTML5\Parser\Event_Handler;

/**
 * This testing class gathers events from a parser and builds a stack of events.
 * It is useful for checking the output of a tokenizer.
 *
 * IMPORTANT:
 *
 * The startTag event also kicks the parser into TEXT_RAW when it encounters
 * script or pre tags. This is to match the behavior required by the HTML5 spec,
 * which says that the tree builder must tell the tokenizer when to switch states.
 */
class Event_Stack implements Event_Handler
{
    protected $stack;

    public function __construct()
    {
        $this->stack = [];
    }

    /**
     * Get the event stack.
     */
    public function events()
    {
        return $this->stack;
    }

    public function depth()
    {
        return count($this->stack);
    }

    public function get($index)
    {
        return $this->stack[$index];
    }

    protected function store($event, $data = null)
    {
        $this->stack[] = [
            'name' => $event,
            'data' => $data,
        ];
    }

    public function doctype($name, $type = 0, $id = null, $quirks = false)
    {
        $args = [
            $name,
            $type,
            $id,
            $quirks,
        ];
        $this->store('doctype', $args);
    }

    public function start_tag($name, $attributes = [], $self_closing = false)
    {
        $args = func_get_args();
        $this->store('startTag', $args);
        if ('pre' == $name || 'script' == $name) {
            return Elements::TEXT_RAW;
        }
    }

    public function end_tag($name)
    {
        $this->store('endTag', [
            $name,
        ]);
    }

    public function comment($cdata)
    {
        $this->store('comment', [
            $cdata,
        ]);
    }

    public function cdata($data)
    {
        $this->store('cdata', func_get_args());
    }

    public function text($cdata)
    {
        // fprintf(STDOUT, "Received TEXT event with: " . $cdata);
        $this->store('text', [
            $cdata,
        ]);
    }

    public function eof()
    {
        $this->store('eof');
    }

    public function parse_error($msg, $line, $col)
    {
        // throw new Event_Stack_Error(sprintf("%s (line %d, col %d)", $msg, $line, $col));
        // $this->store(sprintf("%s (line %d, col %d)", $msg, $line, $col));
        $this->store('error', func_get_args());
    }

    public function processing_instruction($name, $data = null)
    {
        $this->store('pi', func_get_args());
    }
}
