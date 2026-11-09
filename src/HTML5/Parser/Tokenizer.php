<?php

declare (strict_types=1);
namespace Masterminds\HTML5\Parser;

use Masterminds\HTML5\Elements;
/**
 * The HTML5 tokenizer.
 *
 * The tokenizer's role is reading data from the scanner and gathering it into
 * semantic units. From the tokenizer, data is emitted to an event handler,
 * which may (for example) create a DOM tree.
 *
 * The HTML5 specification has a detailed explanation of tokenizing HTML5. We
 * follow that specification to the maximum extent that we can. If you find
 * a discrepancy that is not documented, please file a bug and/or submit a
 * patch.
 *
 * This tokenizer is implemented as a recursive descent parser.
 *
 * Within the API documentation, you may see references to the specific section
 * of the HTML5 spec that the code attempts to reproduce. Example: 8.2.4.1.
 * This refers to section 8.2.4.1 of the HTML5 CR specification.
 *
 * @see http://www.w3.org/TR/2012/CR-html5-20121217/
 */
class Tokenizer
{
    protected $scanner;
    protected $events;
    protected $tok;
    /**
     * Buffer for text.
     */
    protected $text = '';
    // When this goes to false, the parser stops.
    protected $carry_on = true;
    protected $text_mode = 0;
    // TEXTMODE_NORMAL;
    protected $until_tag;
    public const CONFORMANT_XML = 'xml';
    public const CONFORMANT_HTML = 'html';
    protected $mode = self::CONFORMANT_HTML;
    /**
     * Create a new tokenizer.
     *
     * Typically, parsing a document involves creating a new tokenizer, giving
     * it a scanner (input) and an event handler (output), and then calling
     * the Tokenizer::parse() method.`
     *
     * @param Scanner      $scanner      A scanner initialized with an input stream.
     * @param Event_Handler $eventHandler An event handler, initialized and ready to receive events.
     * @param string       $mode
     */
    public function __construct($scanner, $event_handler, $mode = self::CONFORMANT_HTML)
    {
        $this->scanner = $scanner;
        $this->events = $event_handler;
        $this->mode = $mode;
    }
    /**
     * Begin parsing.
     *
     * This will begin scanning the document, tokenizing as it goes.
     * Tokens are emitted into the event handler.
     *
     * Tokenizing will continue until the document is completely
     * read. Errors are emitted into the event handler, but
     * the parser will attempt to continue parsing until the
     * entire input stream is read.
     */
    public function parse()
    {
        do {
            $this->consume_data();
            // FIXME: Add infinite loop protection.
        } while ($this->carry_on);
    }
    /**
     * Set the text mode for the character data reader.
     *
     * HTML5 defines three different modes for reading text:
     * - Normal: Read until a tag is encountered.
     * - RCDATA: Read until a tag is encountered, but skip a few otherwise-
     * special characters.
     * - Raw: Read until a special closing tag is encountered (viz. pre, script)
     *
     * This allows those modes to be set.
     *
     * Normally, setting is done by the event handler via a special return code on
     * startTag(), but it can also be set manually using this function.
     *
     * @param int    $textmode One of Elements::TEXT_*.
     * @param string $untilTag The tag that should stop RAW or RCDATA mode. Normal mode does not
     *                         use this indicator.
     */
    public function set_text_mode($textmode, $until_tag = null)
    {
        $this->text_mode = $textmode & (Elements::TEXT_RAW | Elements::TEXT_RCDATA);
        $this->until_tag = $until_tag;
    }
    /**
     * Consume a character and make a move.
     * HTML5 8.2.4.1.
     */
    protected function consume_data()
    {
        $tok = $this->scanner->current();
        if ('&' === $tok) {
            // Character reference
            $ref = $this->decode_character_reference();
            $this->buffer($ref);
            $tok = $this->scanner->current();
        }
        // Parse tag
        if ('<' === $tok) {
            // Any buffered text data can go out now.
            $this->flush_buffer();
            $tok = $this->scanner->next();
            if (false === $tok) {
                // end of string
                $this->parse_error('Illegal tag opening');
            } elseif ('!' === $tok) {
                $this->markup_declaration();
            } elseif ('/' === $tok) {
                $this->end_tag();
            } elseif ('?' === $tok) {
                $this->processing_instruction();
            } elseif ($this->is_alpha($tok)) {
                $this->tag_name();
            } else {
                $this->parse_error('Illegal tag opening');
                // TODO is this necessary ?
                $this->character_data();
            }
            $tok = $this->scanner->current();
        }
        if (false === $tok) {
            // Handle end of document
            $this->eof();
        } else {
            // Parse character
            switch ($this->text_mode) {
                case Elements::TEXT_RAW:
                    $this->raw_text($tok);
                    break;
                case Elements::TEXT_RCDATA:
                    $this->rcdata($tok);
                    break;
                default:
                    if ('<' === $tok || '&' === $tok) {
                        break;
                    }
                    // NULL character
                    if ("\x00" === $tok) {
                        $this->parse_error('Received null character.');
                        $this->text .= $tok;
                        $this->scanner->consume();
                        break;
                    }
                    $this->text .= $this->scanner->chars_until("<&\x00");
            }
        }
        return $this->carry_on;
    }
    /**
     * Parse anything that looks like character data.
     *
     * Different rules apply based on the current text mode.
     *
     * @see Elements::TEXT_RAW Elements::TEXT_RCDATA.
     */
    protected function character_data()
    {
        $tok = $this->scanner->current();
        if (false === $tok) {
            return false;
        }
        switch ($this->text_mode) {
            case Elements::TEXT_RAW:
                return $this->raw_text($tok);
            case Elements::TEXT_RCDATA:
                return $this->rcdata($tok);
            default:
                if ('<' === $tok || '&' === $tok) {
                    return false;
                }
                return $this->text($tok);
        }
    }
    /**
     * This buffers the current token as character data.
     *
     * @param string $tok The current token.
     *
     * @return bool
     */
    protected function text($tok)
    {
        // This should never happen...
        if (false === $tok) {
            return false;
        }
        // NULL character
        if ("\x00" === $tok) {
            $this->parse_error('Received null character.');
        }
        $this->buffer($tok);
        $this->scanner->consume();
        return true;
    }
    /**
     * Read text in RAW mode.
     *
     * @param string $tok The current token.
     *
     * @return bool
     */
    protected function raw_text($tok)
    {
        if (is_null($this->until_tag)) {
            return $this->text($tok);
        }
        $sequence = '</' . $this->until_tag . '>';
        $txt = $this->read_until_sequence($sequence);
        $this->events->text($txt);
        $this->set_text_mode(0);
        return $this->end_tag();
    }
    /**
     * Read text in RCDATA mode.
     *
     * @param string $tok The current token.
     *
     * @return bool
     */
    protected function rcdata($tok)
    {
        if (is_null($this->until_tag)) {
            return $this->text($tok);
        }
        $sequence = '</' . $this->until_tag;
        $txt = '';
        $case_sensitive = !Elements::is_html5_element($this->until_tag);
        while (false !== $tok && !('<' == $tok && $this->scanner->sequence_matches($sequence, $case_sensitive))) {
            if ('&' == $tok) {
                $txt .= $this->decode_character_reference();
                $tok = $this->scanner->current();
            } else {
                $txt .= $tok;
                $tok = $this->scanner->next();
            }
        }
        $len = strlen($sequence);
        $this->scanner->consume($len);
        $len += $this->scanner->whitespace();
        if ('>' !== $this->scanner->current()) {
            $this->parse_error('Unclosed RCDATA end tag');
        }
        $this->scanner->unconsume($len);
        $this->events->text($txt);
        $this->set_text_mode(0);
        return $this->end_tag();
    }
    /**
     * If the document is read, emit an EOF event.
     */
    protected function eof()
    {
        // fprintf(STDOUT, "EOF");
        $this->flush_buffer();
        $this->events->eof();
        $this->carry_on = false;
    }
    /**
     * Look for markup.
     */
    protected function markup_declaration()
    {
        $tok = $this->scanner->next();
        // Comment:
        if ('-' == $tok && '-' == $this->scanner->peek()) {
            $this->scanner->consume(2);
            return $this->comment();
        }
        if ('D' == $tok || 'd' == $tok) {
            // Doctype
            return $this->doctype();
        }
        // Comment:
        if ('[' == $tok) {
            // CDATA section
            return $this->cdata_section();
        }
        // FINISH
        $this->parse_error('Expected <!--, <![CDATA[, or <!DOCTYPE. Got <!%s', $tok);
        $this->bogus_comment('<!');
        return true;
    }
    /**
     * Consume an end tag. See section 8.2.4.9.
     */
    protected function end_tag()
    {
        if ('/' != $this->scanner->current()) {
            return false;
        }
        $tok = $this->scanner->next();
        // a-zA-Z -> tagname
        // > -> parse error
        // EOF -> parse error
        // -> parse error
        if (!$this->is_alpha($tok)) {
            $this->parse_error("Expected tag name, got '%s'", $tok);
            if ("\x00" == $tok || false === $tok) {
                return false;
            }
            return $this->bogus_comment('</');
        }
        $name = $this->scanner->chars_until("\n\f \t>");
        $name = self::CONFORMANT_XML === $this->mode ? $name : strtolower($name);
        // Trash whitespace.
        $this->scanner->whitespace();
        $tok = $this->scanner->current();
        if ('>' != $tok) {
            $this->parse_error("Expected >, got '%s'", $tok);
            // We just trash stuff until we get to the next tag close.
            $this->scanner->chars_until('>');
        }
        $this->events->end_tag($name);
        $this->scanner->consume();
        return true;
    }
    /**
     * Consume a tag name and body. See section 8.2.4.10.
     */
    protected function tag_name()
    {
        // We know this is at least one char.
        $name = $this->scanner->chars_while(':_-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
        $name = self::CONFORMANT_XML === $this->mode ? $name : strtolower($name);
        $attributes = [];
        $self_close = false;
        // Handle attribute parse exceptions here so that we can
        // react by trying to build a sensible parse tree.
        try {
            do {
                $this->scanner->whitespace();
                $this->attribute($attributes);
            } while (!$this->is_tag_end($self_close));
        } catch (Parse_Error $e) {
            $self_close = false;
        }
        $mode = $this->events->start_tag($name, $attributes, $self_close);
        if (is_int($mode)) {
            $this->set_text_mode($mode, $name);
        }
        $this->scanner->consume();
        return true;
    }
    /**
     * Check if the scanner has reached the end of a tag.
     */
    protected function is_tag_end(&$self_close)
    {
        $tok = $this->scanner->current();
        if ('/' == $tok) {
            $this->scanner->consume();
            $this->scanner->whitespace();
            $tok = $this->scanner->current();
            if ('>' == $tok) {
                $self_close = true;
                return true;
            }
            if (false === $tok) {
                $this->parse_error('Unexpected EOF inside of tag.');
                return true;
            }
            // Basically, we skip the / token and go on.
            // See 8.2.4.43.
            $this->parse_error("Unexpected '%s' inside of a tag.", $tok);
            return false;
        }
        if ('>' == $tok) {
            return true;
        }
        if (false === $tok) {
            $this->parse_error('Unexpected EOF inside of tag.');
            return true;
        }
        return false;
    }
    /**
     * Parse attributes from inside of a tag.
     *
     * @param string[] $attributes
     *
     * @return bool
     *
     * @throws Parse_Error
     */
    protected function attribute(array &$attributes)
    {
        $tok = $this->scanner->current();
        if ('/' == $tok || '>' == $tok || false === $tok) {
            return false;
        }
        if ('<' == $tok) {
            $this->parse_error("Unexpected '<' inside of attributes list.");
            // Push the < back onto the stack.
            $this->scanner->unconsume();
            // Let the caller figure out how to handle this.
            throw new Parse_Error('Start tag inside of attribute.');
        }
        $name = strtolower($this->scanner->chars_until("/>=\n\f\t "));
        if (0 == strlen($name)) {
            $tok = $this->scanner->current();
            $this->parse_error('Expected an attribute name, got %s.', $tok);
            // Really, only '=' can be the char here. Everything else gets absorbed
            // under one rule or another.
            $name = $tok;
            $this->scanner->consume();
        }
        $is_valid_attribute = true;
        // Attribute names can contain most Unicode characters for HTML5.
        // But method "DOMElement::setAttribute" is throwing exception
        // because of it's own internal restriction so these have to be filtered.
        // see issue #23: https://github.com/Masterminds/html5-php/issues/23
        // and http://www.w3.org/TR/2011/WD-html5-20110525/syntax.html#syntax-attribute-name
        if (preg_match("/[\x01-,\\/;-@[-^`{-]/u", $name)) {
            $this->parse_error('Unexpected characters in attribute name: %s', $name);
            $is_valid_attribute = false;
        } elseif (preg_match('/^[0-9.-]/u', $name)) {
            $this->parse_error('Unexpected character at the begining of attribute name: %s', $name);
            $is_valid_attribute = false;
        }
        // 8.1.2.3
        $this->scanner->whitespace();
        $val = $this->attribute_value();
        if ($is_valid_attribute && !array_key_exists($name, $attributes)) {
            $attributes[$name] = $val;
        }
        return true;
    }
    /**
     * Consume an attribute value. See section 8.2.4.37 and after.
     *
     * @return string|null
     */
    protected function attribute_value()
    {
        if ('=' != $this->scanner->current()) {
            return null;
        }
        $this->scanner->consume();
        // 8.1.2.3
        $this->scanner->whitespace();
        $tok = $this->scanner->current();
        switch ($tok) {
            case "\n":
            case "\f":
            case ' ':
            case "\t":
                // Whitespace here indicates an empty value.
                return null;
            case '"':
            case "'":
                $this->scanner->consume();
                return $this->quoted_attribute_value($tok);
            case '>':
                // case '/': // 8.2.4.37 seems to allow foo=/ as a valid attr.
                $this->parse_error('Expected attribute value, got tag end.');
                return null;
            case '=':
            case '`':
                $this->parse_error('Expecting quotes, got %s.', $tok);
                return $this->unquoted_attribute_value();
            default:
                return $this->unquoted_attribute_value();
        }
    }
    /**
     * Get an attribute value string.
     *
     * @param string $quote IMPORTANT: This is a series of chars! Any one of which will be considered
     *                      termination of an attribute's value. E.g. "\"'" will stop at either
     *                      ' or ".
     *
     * @return string The attribute value.
     */
    protected function quoted_attribute_value($quote)
    {
        $stoplist = "\f" . $quote;
        $val = '';
        while (true) {
            $tokens = $this->scanner->chars_until($stoplist . '&');
            if (false !== $tokens) {
                $val .= $tokens;
            } else {
                break;
            }
            $tok = $this->scanner->current();
            if ('&' == $tok) {
                $val .= $this->decode_character_reference(true);
                continue;
            }
            break;
        }
        $this->scanner->consume();
        return $val;
    }
    protected function unquoted_attribute_value()
    {
        $val = '';
        $tok = $this->scanner->current();
        while (false !== $tok) {
            switch ($tok) {
                case "\n":
                case "\f":
                case ' ':
                case "\t":
                case '>':
                    break 2;
                case '&':
                    $val .= $this->decode_character_reference(true);
                    $tok = $this->scanner->current();
                    break;
                case "'":
                case '"':
                case '<':
                case '=':
                case '`':
                    $this->parse_error('Unexpected chars in unquoted attribute value %s', $tok);
                    $val .= $tok;
                    $tok = $this->scanner->next();
                    break;
                default:
                    $val .= $this->scanner->chars_until("\t\n\f >&\"'<=`");
                    $tok = $this->scanner->current();
            }
        }
        return $val;
    }
    /**
     * Consume malformed markup as if it were a comment.
     * 8.2.4.44.
     *
     * The spec requires that the ENTIRE tag-like thing be enclosed inside of
     * the comment. So this will generate comments like:
     *
     * &lt;!--&lt/+foo&gt;--&gt;
     *
     * @param string $leading Prepend any leading characters. This essentially
     *                        negates the need to backtrack, but it's sort of a hack.
     *
     * @return bool
     */
    protected function bogus_comment($leading = '')
    {
        $comment = $leading;
        $tokens = $this->scanner->chars_until('>');
        if (false !== $tokens) {
            $comment .= $tokens;
        }
        $tok = $this->scanner->current();
        if (false !== $tok) {
            $comment .= $tok;
        }
        $this->flush_buffer();
        $this->events->comment($comment);
        $this->scanner->consume();
        return true;
    }
    /**
     * Read a comment.
     * Expects the first tok to be inside of the comment.
     *
     * @return bool
     */
    protected function comment()
    {
        $tok = $this->scanner->current();
        $comment = '';
        // <!-->. Emit an empty comment because 8.2.4.46 says to.
        if ('>' == $tok) {
            // Parse error. Emit the comment token.
            $this->parse_error("Expected comment data, got '>'");
            $this->events->comment('');
            $this->scanner->consume();
            return true;
        }
        // Replace NULL with the replacement char.
        if ("\x00" == $tok) {
            $tok = UTF8Utils::FFFD;
        }
        while (!$this->is_comment_end()) {
            $comment .= $tok;
            $tok = $this->scanner->next();
        }
        $this->events->comment($comment);
        $this->scanner->consume();
        return true;
    }
    /**
     * Check if the scanner has reached the end of a comment.
     *
     * @return bool
     */
    protected function is_comment_end()
    {
        $tok = $this->scanner->current();
        // EOF
        if (false === $tok) {
            // Hit the end.
            $this->parse_error('Unexpected EOF in a comment.');
            return true;
        }
        // If next two tokens are not '--', not the end.
        if ('-' != $tok || '-' != $this->scanner->peek()) {
            return false;
        }
        $this->scanner->consume(2);
        // Consume '-' and one of '!' or '>'
        // Test for '>'
        if ('>' == $this->scanner->current()) {
            return true;
        }
        // Test for '!>'
        if ('!' == $this->scanner->current() && '>' == $this->scanner->peek()) {
            $this->scanner->consume();
            // Consume the last '>'
            return true;
        }
        // Unread '-' and one of '!' or '>';
        $this->scanner->unconsume(2);
        return false;
    }
    /**
     * Parse a DOCTYPE.
     *
     * Parse a DOCTYPE declaration. This method has strong bearing on whether or
     * not Quirksmode is enabled on the event handler.
     *
     * @todo This method is a little long. Should probably refactor.
     *
     * @return bool
     */
    protected function doctype()
    {
        // Check that string is DOCTYPE.
        if ($this->scanner->sequence_matches('DOCTYPE', false)) {
            $this->scanner->consume(7);
        } else {
            $chars = $this->scanner->chars_while('DOCTYPEdoctype');
            $this->parse_error('Expected DOCTYPE, got %s', $chars);
            return $this->bogus_comment('<!' . $chars);
        }
        $this->scanner->whitespace();
        $tok = $this->scanner->current();
        // EOF: die.
        if (false === $tok) {
            $this->events->doctype('html5', Event_Handler::DOCTYPE_NONE, '', true);
            $this->eof();
            return true;
        }
        // NULL char: convert.
        if ("\x00" === $tok) {
            $this->parse_error('Unexpected null character in DOCTYPE.');
        }
        $stop = " \n\f>";
        $doctype_name = $this->scanner->chars_until($stop);
        // Lowercase ASCII, replace \0 with FFFD
        $doctype_name = strtolower(strtr($doctype_name, "\x00", UTF8Utils::FFFD));
        $tok = $this->scanner->current();
        // If false, emit a parse error, DOCTYPE, and return.
        if (false === $tok) {
            $this->parse_error('Unexpected EOF in DOCTYPE declaration.');
            $this->events->doctype($doctype_name, Event_Handler::DOCTYPE_NONE, null, true);
            return true;
        }
        // Short DOCTYPE, like <!DOCTYPE html>
        if ('>' == $tok) {
            // DOCTYPE without a name.
            if (0 == strlen($doctype_name)) {
                $this->parse_error('Expected a DOCTYPE name. Got nothing.');
                $this->events->doctype($doctype_name, 0, null, true);
                $this->scanner->consume();
                return true;
            }
            $this->events->doctype($doctype_name);
            $this->scanner->consume();
            return true;
        }
        $this->scanner->whitespace();
        $pub = strtoupper($this->scanner->get_ascii_alpha());
        $white = $this->scanner->whitespace();
        // Get ID, and flag it as pub or system.
        if (('PUBLIC' == $pub || 'SYSTEM' == $pub) && $white > 0) {
            // Get the sys ID.
            $type = 'PUBLIC' == $pub ? Event_Handler::DOCTYPE_PUBLIC : Event_Handler::DOCTYPE_SYSTEM;
            $id = $this->quoted_string("\x00>");
            if (false === $id) {
                $this->events->doctype($doctype_name, $type, $pub, false);
                return true;
            }
            // Premature EOF.
            if (false === $this->scanner->current()) {
                $this->parse_error('Unexpected EOF in DOCTYPE');
                $this->events->doctype($doctype_name, $type, $id, true);
                return true;
            }
            // Well-formed complete DOCTYPE.
            $this->scanner->whitespace();
            if ('>' == $this->scanner->current()) {
                $this->events->doctype($doctype_name, $type, $id, false);
                $this->scanner->consume();
                return true;
            }
            // If we get here, we have <!DOCTYPE foo PUBLIC "bar" SOME_JUNK
            // Throw away the junk, parse error, quirks mode, return true.
            $this->scanner->chars_until('>');
            $this->parse_error('Malformed DOCTYPE.');
            $this->events->doctype($doctype_name, $type, $id, true);
            $this->scanner->consume();
            return true;
        }
        // Else it's a bogus DOCTYPE.
        // Consume to > and trash.
        $this->scanner->chars_until('>');
        $this->parse_error('Expected PUBLIC or SYSTEM. Got %s.', $pub);
        $this->events->doctype($doctype_name, 0, null, true);
        $this->scanner->consume();
        return true;
    }
    /**
     * Utility for reading a quoted string.
     *
     * @param string $stopchars Characters (in addition to a close-quote) that should stop the string.
     *                          E.g. sometimes '>' is higher precedence than '"' or "'".
     *
     * @return mixed String if one is found (quotations omitted).
     */
    protected function quoted_string($stopchars)
    {
        $tok = $this->scanner->current();
        if ('"' == $tok || "'" == $tok) {
            $this->scanner->consume();
            $ret = $this->scanner->chars_until($tok . $stopchars);
            if ($this->scanner->current() == $tok) {
                $this->scanner->consume();
            } else {
                // Parse error because no close quote.
                $this->parse_error('Expected %s, got %s', $tok, $this->scanner->current());
            }
            return $ret;
        }
        return false;
    }
    /**
     * Handle a CDATA section.
     *
     * @return bool
     */
    protected function cdata_section()
    {
        $cdata = '';
        $this->scanner->consume();
        $chars = $this->scanner->chars_while('CDAT');
        if ('CDATA' != $chars || '[' != $this->scanner->current()) {
            $this->parse_error('Expected [CDATA[, got %s', $chars);
            return $this->bogus_comment('<![' . $chars);
        }
        $tok = $this->scanner->next();
        do {
            if (false === $tok) {
                $this->parse_error('Unexpected EOF inside CDATA.');
                $this->bogus_comment('<![CDATA[' . $cdata);
                return true;
            }
            $cdata .= $tok;
            $tok = $this->scanner->next();
        } while (!$this->scanner->sequence_matches(']]>'));
        // Consume ]]>
        $this->scanner->consume(3);
        $this->events->cdata($cdata);
        return true;
    }
    // ================================================================
    // Non-HTML5
    // ================================================================
    /**
     * Handle a processing instruction.
     *
     * XML processing instructions are supposed to be ignored in HTML5,
     * treated as "bogus comments". However, since we're not a user
     * agent, we allow them. We consume until ?> and then issue a
     * EventListener::processingInstruction() event.
     *
     * @return bool
     */
    protected function processing_instruction()
    {
        if ('?' != $this->scanner->current()) {
            return false;
        }
        $tok = $this->scanner->next();
        $proc_name = $this->scanner->get_ascii_alpha();
        $white = $this->scanner->whitespace();
        // If not a PI, send to bogusComment.
        if (0 == strlen($proc_name) || 0 == $white || false == $this->scanner->current()) {
            $this->parse_error("Expected processing instruction name, got {$tok}");
            $this->bogus_comment('<?' . $tok . $proc_name);
            return true;
        }
        $data = '';
        // As long as it's not the case that the next two chars are ? and >.
        while (!('?' == $this->scanner->current() && '>' == $this->scanner->peek())) {
            $data .= $this->scanner->current();
            $tok = $this->scanner->next();
            if (false === $tok) {
                $this->parse_error('Unexpected EOF in processing instruction.');
                $this->events->processing_instruction($proc_name, $data);
                return true;
            }
        }
        $this->scanner->consume(2);
        // Consume the closing tag
        $this->events->processing_instruction($proc_name, $data);
        return true;
    }
    // ================================================================
    // UTILITY FUNCTIONS
    // ================================================================
    /**
     * Read from the input stream until we get to the desired sequene
     * or hit the end of the input stream.
     *
     * @param string $sequence
     *
     * @return string
     */
    protected function read_until_sequence($sequence)
    {
        $buffer = '';
        // Optimization for reading larger blocks faster.
        $first = substr($sequence, 0, 1);
        while (false !== $this->scanner->current()) {
            $buffer .= $this->scanner->chars_until($first);
            // Stop as soon as we hit the stopping condition.
            if ($this->scanner->sequence_matches($sequence, false)) {
                return $buffer;
            }
            $buffer .= $this->scanner->current();
            $this->scanner->consume();
        }
        // If we get here, we hit the EOF.
        $this->parse_error('Unexpected EOF during text read.');
        return $buffer;
    }
    /**
     * Check if upcomming chars match the given sequence.
     *
     * This will read the stream for the $sequence. If it's
     * found, this will return true. If not, return false.
     * Since this unconsumes any chars it reads, the caller
     * will still need to read the next sequence, even if
     * this returns true.
     *
     * Example: $this->scanner->sequenceMatches('</script>') will
     * see if the input stream is at the start of a
     * '</script>' string.
     *
     * @param string $sequence
     * @param bool   $caseSensitive
     *
     * @return bool
     */
    protected function sequence_matches($sequence, $case_sensitive = true)
    {
        @trigger_error(__METHOD__ . ' method is deprecated since version 2.4 and will be removed in 3.0. Use Scanner::sequenceMatches() instead.', E_USER_DEPRECATED);
        return $this->scanner->sequence_matches($sequence, $case_sensitive);
    }
    /**
     * Send a TEXT event with the contents of the text buffer.
     *
     * This emits an Event_Handler::text() event with the current contents of the
     * temporary text buffer. (The buffer is used to group as much PCDATA
     * as we can instead of emitting lots and lots of TEXT events.)
     */
    protected function flush_buffer()
    {
        if ('' === $this->text) {
            return;
        }
        $this->events->text($this->text);
        $this->text = '';
    }
    /**
     * Add text to the temporary buffer.
     *
     * @see flushBuffer()
     *
     * @param string $str
     */
    protected function buffer($str)
    {
        $this->text .= $str;
    }
    /**
     * Emit a parse error.
     *
     * A parse error always returns false because it never consumes any
     * characters.
     *
     * @param string $msg
     *
     * @return string
     */
    protected function parse_error($msg)
    {
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            $msg = vsprintf($msg, $args);
        }
        $line = $this->scanner->current_line();
        $col = $this->scanner->column_offset();
        $this->events->parse_error($msg, $line, $col);
        return false;
    }
    /**
     * Decode a character reference and return the string.
     *
     * If $inAttribute is set to true, a bare & will be returned as-is.
     *
     * @param bool $inAttribute Set to true if the text is inside of an attribute value.
     *                          false otherwise.
     *
     * @return string
     */
    protected function decode_character_reference($in_attribute = false)
    {
        // Next char after &.
        $tok = $this->scanner->next();
        $start = $this->scanner->position();
        if (false === $tok) {
            return '&';
        }
        // These indicate not an entity. We return just
        // the &.
        if ("\t" === $tok || "\n" === $tok || "\f" === $tok || ' ' === $tok || '&' === $tok || '<' === $tok) {
            // $this->scanner->next();
            return '&';
        }
        // Numeric entity
        if ('#' === $tok) {
            $tok = $this->scanner->next();
            if (false === $tok) {
                $this->parse_error('Expected &#DEC; &#HEX;, got EOF');
                $this->scanner->unconsume(1);
                return '&';
            }
            // Hexadecimal encoding.
            // X[0-9a-fA-F]+;
            // x[0-9a-fA-F]+;
            if ('x' === $tok || 'X' === $tok) {
                $tok = $this->scanner->next();
                // Consume x
                // Convert from hex code to char.
                $hex = $this->scanner->get_hex();
                if (empty($hex)) {
                    $this->parse_error('Expected &#xHEX;, got &#x%s', $tok);
                    // We unconsume because we don't know what parser rules might
                    // be in effect for the remaining chars. For example. '&#>'
                    // might result in a specific parsing rule inside of tag
                    // contexts, while not inside of pcdata context.
                    $this->scanner->unconsume(2);
                    return '&';
                }
                $entity = Character_Reference::lookup_hex($hex);
            } else {
                // Convert from decimal to char.
                $numeric = $this->scanner->get_numeric();
                if (false === $numeric) {
                    $this->parse_error('Expected &#DIGITS;, got &#%s', $tok);
                    $this->scanner->unconsume(2);
                    return '&';
                }
                $entity = Character_Reference::lookup_decimal($numeric);
            }
        } elseif ('=' === $tok && $in_attribute) {
            return '&';
        } else {
            // String entity.
            // Attempt to consume a string up to a ';'.
            // [a-zA-Z0-9]+;
            $cname = $this->scanner->get_ascii_alpha_num();
            $entity = Character_Reference::lookup_name($cname);
            // When no entity is found provide the name of the unmatched string
            // and continue on as the & is not part of an entity. The & will
            // be converted to &amp; elsewhere.
            if (null === $entity) {
                if (!$in_attribute || '' === $cname) {
                    $this->parse_error("No match in entity table for '%s'", $cname);
                }
                $this->scanner->unconsume($this->scanner->position() - $start);
                return '&';
            }
        }
        // The scanner has advanced the cursor for us.
        $tok = $this->scanner->current();
        // We have an entity. We're done here.
        if (';' === $tok) {
            $this->scanner->consume();
            return $entity;
        }
        // Failing to match ; means unconsume the entire string.
        $this->scanner->unconsume($this->scanner->position() - $start);
        $this->parse_error('Expected &ENTITY;, got &ENTITY%s (no trailing ;) ', $tok);
        return '&';
    }
    /**
     * Checks whether a (single-byte) character is an ASCII letter or not.
     *
     * @param string $input A single-byte string
     *
     * @return bool True if it is a letter, False otherwise
     */
    protected function is_alpha($input)
    {
        $code = ord($input);
        return $code >= 97 && $code <= 122 || $code >= 65 && $code <= 90;
    }
}