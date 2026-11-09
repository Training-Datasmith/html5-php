<?php

declare (strict_types=1);
namespace Masterminds\HTML5\Parser;

use Masterminds\HTML5\Elements;
use Masterminds\HTML5\Instruction_Processor;
/**
 * Create an HTML5 DOM tree from events.
 *
 * This attempts to create a DOM from events emitted by a parser. This
 * attempts (but does not guarantee) to up-convert older HTML documents
 * to HTML5. It does this by applying HTML5's rules, but it will not
 * change the architecture of the document itself.
 *
 * Many of the error correction and quirks features suggested in the specification
 * are implemented herein; however, not all of them are. Since we do not
 * assume a graphical user agent, no presentation-specific logic is conducted
 * during tree building.
 *
 * FIXME: The present tree builder does not exactly follow the state machine rules
 * for insert modes as outlined in the HTML5 spec. The processor needs to be
 * re-written to accomodate this. See, for example, the Go language HTML5
 * parser.
 */
class DOM_Tree_Builder implements Event_Handler
{
    /**
     * Defined in http://www.w3.org/TR/html51/infrastructure.html#html-namespace-0.
     */
    public const NAMESPACE_HTML = 'http://www.w3.org/1999/xhtml';
    public const NAMESPACE_MATHML = 'http://www.w3.org/1998/Math/MathML';
    public const NAMESPACE_SVG = 'http://www.w3.org/2000/svg';
    public const NAMESPACE_XLINK = 'http://www.w3.org/1999/xlink';
    public const NAMESPACE_XML = 'http://www.w3.org/XML/1998/namespace';
    public const NAMESPACE_XMLNS = 'http://www.w3.org/2000/xmlns/';
    public const OPT_DISABLE_HTML_NS = 'disable_html_ns';
    public const OPT_TARGET_DOC = 'target_document';
    public const OPT_IMPLICIT_NS = 'implicit_namespaces';
    /**
     * Holds the HTML5 element names that causes a namespace switch.
     *
     * @var array
     */
    protected $ns_roots = ['html' => self::NAMESPACE_HTML, 'svg' => self::NAMESPACE_SVG, 'math' => self::NAMESPACE_MATHML];
    /**
     * Holds the always available namespaces (which does not require the XMLNS declaration).
     *
     * @var array
     */
    protected $implicit_namespaces = ['xml' => self::NAMESPACE_XML, 'xmlns' => self::NAMESPACE_XMLNS, 'xlink' => self::NAMESPACE_XLINK];
    /**
     * Holds a stack of currently active namespaces.
     *
     * @var array
     */
    protected $ns_stack = [];
    /**
     * Holds the number of namespaces declared by a node.
     *
     * @var array
     */
    protected $pushes = [];
    /**
     * Defined in 8.2.5.
     */
    public const IM_INITIAL = 0;
    public const IM_BEFORE_HTML = 1;
    public const IM_BEFORE_HEAD = 2;
    public const IM_IN_HEAD = 3;
    public const IM_IN_HEAD_NOSCRIPT = 4;
    public const IM_AFTER_HEAD = 5;
    public const IM_IN_BODY = 6;
    public const IM_TEXT = 7;
    public const IM_IN_TABLE = 8;
    public const IM_IN_TABLE_TEXT = 9;
    public const IM_IN_CAPTION = 10;
    public const IM_IN_COLUMN_GROUP = 11;
    public const IM_IN_TABLE_BODY = 12;
    public const IM_IN_ROW = 13;
    public const IM_IN_CELL = 14;
    public const IM_IN_SELECT = 15;
    public const IM_IN_SELECT_IN_TABLE = 16;
    public const IM_AFTER_BODY = 17;
    public const IM_IN_FRAMESET = 18;
    public const IM_AFTER_FRAMESET = 19;
    public const IM_AFTER_AFTER_BODY = 20;
    public const IM_AFTER_AFTER_FRAMESET = 21;
    public const IM_IN_SVG = 22;
    public const IM_IN_MATHML = 23;
    protected $options = [];
    protected $stack = [];
    protected $current;
    // Pointer in the tag hierarchy.
    protected $rules;
    protected $doc;
    protected $frag;
    protected $processor;
    protected $insert_mode = 0;
    /**
     * Track if we are in an element that allows only inline child nodes.
     *
     * @var string|null
     */
    protected $only_inline;
    /**
     * Quirks mode is enabled by default.
     * Any document that is missing the DT will be considered to be in quirks mode.
     */
    protected $quirks = true;
    protected $errors = [];
    public function __construct($is_fragment = false, array $options = [])
    {
        $this->options = $options;
        if (isset($options[self::OPT_TARGET_DOC])) {
            $this->doc = $options[self::OPT_TARGET_DOC];
        } else {
            $impl = new \DOMImplementation();
            // XXX:
            // Create the doctype. For now, we are always creating HTML5
            // documents, and attempting to up-convert any older DTDs to HTML5.
            $dt = $impl->createDocumentType('html');
            // $this->doc = \DOMImplementation::createDocument(NULL, 'html', $dt);
            $this->doc = $impl->createDocument(null, '', $dt);
            $this->doc->encoding = !empty($options['encoding']) ? $options['encoding'] : 'UTF-8';
        }
        $this->errors = [];
        $this->current = $this->doc;
        // ->documentElement;
        // Create a rules engine for tags.
        $this->rules = new Tree_Building_Rules();
        $implicit_ns = [];
        if (isset($this->options[self::OPT_IMPLICIT_NS])) {
            $implicit_ns = $this->options[self::OPT_IMPLICIT_NS];
        }
        // Fill $nsStack with the defalut HTML5 namespaces, plus the "implicit_namespaces" array taken form $options
        array_unshift($this->ns_stack, $implicit_ns + ['' => self::NAMESPACE_HTML] + $this->implicit_namespaces);
        if ($is_fragment) {
            $this->insert_mode = static::IM_IN_BODY;
            $this->frag = $this->doc->createDocumentFragment();
            $this->current = $this->frag;
        }
    }
    /**
     * Get the document.
     */
    public function document()
    {
        return $this->doc;
    }
    /**
     * Get the DOM fragment for the body.
     *
     * This returns a DOMNodeList because a fragment may have zero or more
     * DOMNodes at its root.
     *
     * @see http://www.w3.org/TR/2012/CR-html5-20121217/syntax.html#concept-frag-parse-context
     *
     * @return \DOMDocumentFragment
     */
    public function fragment()
    {
        return $this->frag;
    }
    /**
     * Provide an instruction processor.
     *
     * This is used for handling Processor Instructions as they are
     * inserted. If omitted, PI's are inserted directly into the DOM tree.
     */
    public function set_instruction_processor(Instruction_Processor $proc)
    {
        $this->processor = $proc;
    }
    public function doctype($name, $id_type = 0, $id = null, $quirks = false)
    {
        // This is used solely for setting quirks mode. Currently we don't
        // try to preserve the inbound DT. We convert it to HTML5.
        $this->quirks = $quirks;
        if ($this->insert_mode > static::IM_INITIAL) {
            $this->parse_error('Illegal placement of DOCTYPE tag. Ignoring: ' . $name);
            return;
        }
        $this->insert_mode = static::IM_BEFORE_HTML;
    }
    /**
     * Process the start tag.
     *
     * @todo - XMLNS namespace handling (we need to parse, even if it's not valid)
     *       - XLink, MathML and SVG namespace handling
     *       - Omission rules: 8.1.2.4 Optional tags
     *
     * @param string $name
     * @param array  $attributes
     * @param bool   $selfClosing
     *
     * @return int
     */
    public function start_tag($name, $attributes = [], $self_closing = false)
    {
        $lname = $this->normalize_tag_name($name);
        // Make sure we have an html element.
        if (!$this->doc->documentElement && 'html' !== $name && !$this->frag) {
            $this->start_tag('html');
        }
        // Set quirks mode if we're at IM_INITIAL with no doctype.
        if ($this->insert_mode === static::IM_INITIAL) {
            $this->quirks = true;
            $this->parse_error('No DOCTYPE specified.');
        }
        // SPECIAL TAG HANDLING:
        // Spec says do this, and "don't ask."
        // find the spec where this is defined... looks problematic
        if ('image' === $name && !($this->insert_mode === static::IM_IN_SVG || $this->insert_mode === static::IM_IN_MATHML)) {
            $name = 'img';
        }
        // Autoclose p tags where appropriate.
        if ($this->insert_mode >= static::IM_IN_BODY && Elements::is_a($name, Elements::AUTOCLOSE_P)) {
            $this->autoclose('p');
        }
        // Set insert mode:
        switch ($name) {
            case 'html':
                $this->insert_mode = static::IM_BEFORE_HEAD;
                break;
            case 'head':
                if ($this->insert_mode > static::IM_BEFORE_HEAD) {
                    $this->parse_error('Unexpected head tag outside of head context.');
                } else {
                    $this->insert_mode = static::IM_IN_HEAD;
                }
                break;
            case 'body':
                $this->insert_mode = static::IM_IN_BODY;
                break;
            case 'svg':
                $this->insert_mode = static::IM_IN_SVG;
                break;
            case 'math':
                $this->insert_mode = static::IM_IN_MATHML;
                break;
            case 'noscript':
                if ($this->insert_mode === static::IM_IN_HEAD) {
                    $this->insert_mode = static::IM_IN_HEAD_NOSCRIPT;
                }
                break;
        }
        // Special case handling for SVG.
        if ($this->insert_mode === static::IM_IN_SVG) {
            $lname = Elements::normalize_svg_element($lname);
        }
        $pushes = 0;
        // when we found a tag thats appears inside $nsRoots, we have to switch the defalut namespace
        if (isset($this->ns_roots[$lname]) && $this->ns_stack[0][''] !== $this->ns_roots[$lname]) {
            array_unshift($this->ns_stack, ['' => $this->ns_roots[$lname]] + $this->ns_stack[0]);
            ++$pushes;
        }
        $needs_workaround = false;
        if (isset($this->options['xml_namespaces']) && $this->options['xml_namespaces']) {
            // when xml_namespaces is true a and we found a 'xmlns' or 'xmlns:*' attribute, we should add a new item to the $nsStack
            foreach ($attributes as $a_name => $a_val) {
                if ('xmlns' === $a_name) {
                    $needs_workaround = $a_val;
                    array_unshift($this->ns_stack, ['' => $a_val] + $this->ns_stack[0]);
                    ++$pushes;
                } elseif ('xmlns' === (($pos = strpos($a_name, ':')) ? substr($a_name, 0, $pos) : '')) {
                    array_unshift($this->ns_stack, [substr($a_name, $pos + 1) => $a_val] + $this->ns_stack[0]);
                    ++$pushes;
                }
            }
        }
        if ($this->only_inline && Elements::is_a($lname, Elements::BLOCK_TAG)) {
            $this->autoclose($this->only_inline);
            $this->only_inline = null;
        }
        // some elements as table related tags might have optional end tags that force us to auto close multiple tags
        // https://www.w3.org/TR/html401/struct/tables.html
        if ($this->current instanceof \DOMElement && isset(Elements::$optional_end_elements_parents_to_close[$lname])) {
            foreach (Elements::$optional_end_elements_parents_to_close[$lname] as $parent_el_name) {
                if ($this->current instanceof \DOMElement && $this->current->tagName === $parent_el_name) {
                    $this->autoclose($parent_el_name);
                }
            }
        }
        try {
            $prefix = ($pos = strpos($lname, ':')) ? substr($lname, 0, $pos) : '';
            if (false !== $needs_workaround) {
                $safe_ns = htmlspecialchars((string) $needs_workaround, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $safe_ns_prefix = strlen($prefix) && isset($this->ns_stack[0][$prefix]) ? htmlspecialchars((string) $this->ns_stack[0][$prefix], ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;
                $xml = "<{$lname} xmlns=\"{$safe_ns}\" " . ($safe_ns_prefix !== null ? "xmlns:{$prefix}=\"" . $safe_ns_prefix . '"' : '') . '/>';
                $frag = new \DOMDocument('1.0', 'UTF-8');
                $frag->loadXML($xml);
                $ele = $this->doc->importNode($frag->documentElement, true);
            } else if (!isset($this->ns_stack[0][$prefix]) || '' === $prefix && isset($this->options[self::OPT_DISABLE_HTML_NS]) && $this->options[self::OPT_DISABLE_HTML_NS]) {
                $ele = $this->doc->createElement($lname);
            } else {
                $ele = $this->doc->createElementNS($this->ns_stack[0][$prefix], $lname);
            }
        } catch (\DOMException $e) {
            $this->parse_error("Illegal tag name: <{$lname}>. Replaced with <invalid>.");
            $ele = $this->doc->createElement('invalid');
        }
        if (Elements::is_a($lname, Elements::BLOCK_ONLY_INLINE)) {
            $this->only_inline = $lname;
        }
        // When we add some namespacess, we have to track them. Later, when "endElement" is invoked, we have to remove them.
        // When we are on a void tag, we do not need to care about namesapce nesting.
        if ($pushes > 0 && !Elements::is_a($name, Elements::VOID_TAG)) {
            // PHP tends to free the memory used by DOM,
            // to avoid spl_object_hash collisions whe have to avoid garbage collection of $ele storing it into $pushes
            // see https://bugs.php.net/bug.php?id=67459
            $this->pushes[spl_object_hash($ele)] = [$pushes, $ele];
        }
        foreach ($attributes as $a_name => $a_val) {
            // xmlns attributes can't be set
            if ('xmlns' === $a_name) {
                continue;
            }
            if ($this->insert_mode === static::IM_IN_SVG) {
                $a_name = Elements::normalize_svg_attribute($a_name);
            } elseif ($this->insert_mode === static::IM_IN_MATHML) {
                $a_name = Elements::normalize_math_ml_attribute($a_name);
            }
            $a_val = (string) $a_val;
            try {
                $prefix = ($pos = strpos($a_name, ':')) ? substr($a_name, 0, $pos) : false;
                if ('xmlns' === $prefix) {
                    $ele->setAttributeNS(self::NAMESPACE_XMLNS, $a_name, $a_val);
                } elseif (false !== $prefix && isset($this->ns_stack[0][$prefix])) {
                    $ele->setAttributeNS($this->ns_stack[0][$prefix], $a_name, $a_val);
                } else {
                    $ele->setAttribute($a_name, $a_val);
                }
            } catch (\DOMException $e) {
                $this->parse_error("Illegal attribute name for tag {$name}. Ignoring: {$a_name}");
                continue;
            }
            // This is necessary on a non-DTD schema, like HTML5.
            if ('id' === $a_name) {
                $ele->setIdAttribute('id', true);
            }
        }
        if ($this->frag !== $this->current && $this->rules->has_rules($name)) {
            // Some elements have special processing rules. Handle those separately.
            $this->current = $this->rules->evaluate($ele, $this->current);
        } else {
            // Otherwise, it's a standard element.
            $this->current->appendChild($ele);
            if (!Elements::is_a($name, Elements::VOID_TAG)) {
                $this->current = $ele;
            }
            // Self-closing tags should only be respected on foreign elements
            // (and are implied on void elements)
            // See: https://www.w3.org/TR/html5/syntax.html#start-tags
            if (Elements::is_html5_element($name)) {
                $self_closing = false;
            }
        }
        // This is sort of a last-ditch attempt to correct for cases where no head/body
        // elements are provided.
        if ($this->insert_mode <= static::IM_BEFORE_HEAD && 'head' !== $name && 'html' !== $name) {
            $this->insert_mode = static::IM_IN_BODY;
        }
        // When we are on a void tag, we do not need to care about namesapce nesting,
        // but we have to remove the namespaces pushed to $nsStack.
        if ($pushes > 0 && Elements::is_a($name, Elements::VOID_TAG)) {
            // remove the namespaced definded by current node
            for ($i = 0; $i < $pushes; ++$i) {
                array_shift($this->ns_stack);
            }
        }
        if ($self_closing) {
            $this->end_tag($name);
        }
        // Return the element mask, which the tokenizer can then use to set
        // various processing rules.
        return Elements::element($name);
    }
    public function end_tag($name)
    {
        $lname = $this->normalize_tag_name($name);
        // Special case within 12.2.6.4.7: An end tag whose tag name is "br" should be treated as an opening tag
        if ('br' === $name) {
            $this->parse_error('Closing tag encountered for void element br.');
            $this->start_tag('br');
        } elseif (Elements::is_a($name, Elements::VOID_TAG)) {
            return;
        }
        if ($this->insert_mode <= static::IM_BEFORE_HTML) {
            // 8.2.5.4.2
            if (in_array($name, ['html', 'br', 'head', 'title'])) {
                $this->start_tag('html');
                $this->end_tag($name);
                $this->insert_mode = static::IM_BEFORE_HEAD;
                return;
            }
            // Ignore the tag.
            $this->parse_error('Illegal closing tag at global scope.');
            return;
        }
        // Special case handling for SVG.
        if ($this->insert_mode === static::IM_IN_SVG) {
            $lname = Elements::normalize_svg_element($lname);
        }
        $cid = spl_object_hash($this->current);
        // XXX: HTML has no parent. What do we do, though,
        // if this element appears in the wrong place?
        if ('html' === $lname) {
            return;
        }
        // remove the namespaced definded by current node
        if (isset($this->pushes[$cid])) {
            for ($i = 0; $i < $this->pushes[$cid][0]; ++$i) {
                array_shift($this->ns_stack);
            }
            unset($this->pushes[$cid]);
        }
        if (!$this->autoclose($lname)) {
            $this->parse_error('Could not find closing tag for ' . $lname);
        }
        switch ($lname) {
            case 'head':
                $this->insert_mode = static::IM_AFTER_HEAD;
                break;
            case 'body':
                $this->insert_mode = static::IM_AFTER_BODY;
                break;
            case 'svg':
            case 'mathml':
                $this->insert_mode = static::IM_IN_BODY;
                break;
        }
    }
    public function comment($cdata)
    {
        // TODO: Need to handle case where comment appears outside of the HTML tag.
        $node = $this->doc->createComment($cdata);
        $this->current->appendChild($node);
    }
    public function text($data)
    {
        // XXX: Hmmm.... should we really be this strict?
        if ($this->insert_mode < static::IM_IN_HEAD) {
            // Per '8.2.5.4.3 The "before head" insertion mode' the characters
            // " \t\n\r\f" should be ignored but no mention of a parse error. This is
            // practical as most documents contain these characters. Other text is not
            // expected here so recording a parse error is necessary.
            $data_tmp = trim($data, " \t\n\r\f");
            if (!empty($data_tmp)) {
                // fprintf(STDOUT, "Unexpected insert mode: %d", $this->insertMode);
                $this->parse_error('Unexpected text. Ignoring: ' . $data_tmp);
            }
            return;
        }
        // fprintf(STDOUT, "Appending text %s.", $data);
        $node = $this->doc->createTextNode($data);
        $this->current->appendChild($node);
    }
    public function eof()
    {
        // If the $current isn't the $root, do we need to do anything?
    }
    public function parse_error($msg, $line = 0, $col = 0)
    {
        $this->errors[] = sprintf('Line %d, Col %d: %s', $line, $col, $msg);
    }
    public function get_errors()
    {
        return $this->errors;
    }
    public function cdata($data)
    {
        $node = $this->doc->createCDATASection($data);
        $this->current->appendChild($node);
    }
    public function processing_instruction($name, $data = null)
    {
        // XXX: Ignore initial XML declaration, per the spec.
        if ($this->insert_mode === static::IM_INITIAL && 'xml' === strtolower($name)) {
            return;
        }
        // Important: The processor may modify the current DOM tree however it sees fit.
        if ($this->processor instanceof Instruction_Processor) {
            $res = $this->processor->process($this->current, $name, $data);
            if (!empty($res)) {
                $this->current = $res;
            }
            return;
        }
        // Otherwise, this is just a dumb PI element.
        $node = $this->doc->createProcessingInstruction($name, $data);
        $this->current->appendChild($node);
    }
    // ==========================================================================
    // UTILITIES
    // ==========================================================================
    /**
     * Apply normalization rules to a tag name.
     * See sections 2.9 and 8.1.2.
     *
     * @param string $tagName
     *
     * @return string The normalized tag name.
     */
    protected function normalize_tag_name($tag_name)
    {
        /*
         * Section 2.9 suggests that we should not do this. if (strpos($name, ':') !== false) { // We know from the grammar that there must be at least one other // char besides :, since : is not a legal tag start. $parts = explode(':', $name); return array_pop($parts); }
         */
        return $tag_name;
    }
    protected function quirks_tree_resolver($name)
    {
        throw new \Exception('Not implemented.');
    }
    /**
     * Automatically climb the tree and close the closest node with the matching $tag.
     *
     * @param string $tagName
     *
     * @return bool
     */
    protected function autoclose($tag_name)
    {
        $working = $this->current;
        do {
            if (XML_ELEMENT_NODE !== $working->nodeType) {
                return false;
            }
            if ($working->tagName === $tag_name) {
                $this->current = $working->parentNode;
                return true;
            }
        } while ($working = $working->parentNode);
        return false;
    }
    /**
     * Checks if the given tagname is an ancestor of the present candidate.
     *
     * If $this->current or anything above $this->current matches the given tag
     * name, this returns true.
     *
     * @param string $tagName
     *
     * @return bool
     */
    protected function is_ancestor($tag_name)
    {
        $candidate = $this->current;
        while (XML_ELEMENT_NODE === $candidate->nodeType) {
            if ($candidate->tagName === $tag_name) {
                return true;
            }
            $candidate = $candidate->parentNode;
        }
        return false;
    }
    /**
     * Returns true if the immediate parent element is of the given tagname.
     *
     * @param string $tagName
     *
     * @return bool
     */
    protected function is_parent($tag_name)
    {
        return $this->current->tagName === $tag_name;
    }
}