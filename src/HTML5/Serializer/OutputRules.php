<?php

declare (strict_types=1);
/**
 * @file
 * The rules for generating output in the serializer.
 *
 * These output rules are likely to generate output similar to the document that
 * was parsed. It is not intended to output exactly the document that was parsed.
 */
namespace Masterminds\HTML5\Serializer;

use Masterminds\HTML5\Elements;
/**
 * Generate the output html5 based on element rules.
 */
class Output_Rules implements Rules_Interface
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
    /**
     * Holds the HTML5 element names that causes a namespace switch.
     *
     * @var array
     */
    protected $implicit_namespaces = [self::NAMESPACE_HTML, self::NAMESPACE_SVG, self::NAMESPACE_MATHML, self::NAMESPACE_XML, self::NAMESPACE_XMLNS];
    public const IM_IN_HTML = 1;
    public const IM_IN_SVG = 2;
    public const IM_IN_MATHML = 3;
    /**
     * Used as cache to detect if is available ENT_HTML5.
     *
     * @var bool
     */
    private $has_html5 = false;
    protected $traverser;
    protected $encode = false;
    protected $out;
    protected $output_mode;
    private $xpath;
    protected $non_boolean_attributes = [
        /*
        array(
            'nodeNamespace'=>'http://www.w3.org/1999/xhtml',
            'attrNamespace'=>'http://www.w3.org/1999/xhtml',
        
            'nodeName'=>'img', 'nodeName'=>array('img', 'a'),
            'attrName'=>'alt', 'attrName'=>array('title', 'alt'),
        ),
        */
        ['nodeNamespace' => 'http://www.w3.org/1999/xhtml', 'attrName' => ['href', 'hreflang', 'http-equiv', 'icon', 'id', 'keytype', 'kind', 'label', 'lang', 'language', 'list', 'maxlength', 'media', 'method', 'name', 'placeholder', 'rel', 'rows', 'rowspan', 'sandbox', 'spellcheck', 'scope', 'seamless', 'shape', 'size', 'sizes', 'span', 'src', 'srcdoc', 'srclang', 'srcset', 'start', 'step', 'style', 'summary', 'tabindex', 'target', 'title', 'type', 'value', 'width', 'border', 'charset', 'cite', 'class', 'code', 'codebase', 'color', 'cols', 'colspan', 'content', 'coords', 'data', 'datetime', 'default', 'dir', 'dirname', 'enctype', 'for', 'form', 'formaction', 'headers', 'height', 'accept', 'accept-charset', 'accesskey', 'action', 'align', 'alt', 'bgcolor']],
        ['nodeNamespace' => 'http://www.w3.org/1999/xhtml', 'xpath' => 'starts-with(local-name(), \'data-\')'],
    ];
    public const DOCTYPE = '<!DOCTYPE html>';
    public function __construct($output, $options = [])
    {
        if (isset($options['encode_entities'])) {
            $this->encode = $options['encode_entities'];
        }
        $this->output_mode = static::IM_IN_HTML;
        $this->out = $output;
        $this->has_html5 = defined('ENT_HTML5');
    }
    public function add_rule(array $rule)
    {
        $this->non_boolean_attributes[] = $rule;
    }
    public function set_traverser(Traverser $traverser)
    {
        $this->traverser = $traverser;
        return $this;
    }
    public function unset_traverser()
    {
        $this->traverser = null;
        return $this;
    }
    public function document($dom)
    {
        $this->doctype();
        if ($dom->document_element) {
            foreach ($dom->child_nodes as $node) {
                $this->traverser->node($node);
            }
            $this->nl();
        }
    }
    protected function doctype()
    {
        $this->wr(static::DOCTYPE);
        $this->nl();
    }
    /**
     * @param \DOMElement $ele
     */
    public function element($ele)
    {
        $name = $ele->tag_name;
        // Per spec:
        // If the element has a declared namespace in the HTML, MathML or
        // SVG namespaces, we use the lname instead of the tagName.
        if ($this->traverser->is_local_element($ele)) {
            $name = $ele->local_name;
        }
        // If we are in SVG or MathML there is special handling.
        // Using if/elseif instead of switch because it's faster in PHP.
        if ('svg' == $name) {
            $this->output_mode = static::IM_IN_SVG;
            $name = Elements::normalize_svg_element($name);
        } elseif ('math' == $name) {
            $this->output_mode = static::IM_IN_MATHML;
        }
        $this->open_tag($ele);
        // The tag is already self-closed (`<svg />` or `<math />`) in `openTag` if there are no child nodes.
        $handled_as_void_tag = $this->output_mode !== static::IM_IN_HTML && !$ele->has_child_nodes();
        if (Elements::is_a($name, Elements::TEXT_RAW)) {
            foreach ($ele->child_nodes as $child) {
                if ($child instanceof \Dom_Character_Data) {
                    $this->wr($child->data);
                } elseif ($child instanceof \Dom_Element) {
                    $this->element($child);
                }
            }
        } else {
            // Handle children.
            if ($ele->has_child_nodes()) {
                $this->traverser->children($ele->child_nodes);
            }
            // Close out the SVG or MathML special handling.
            if ('svg' == $name || 'math' == $name) {
                $this->output_mode = static::IM_IN_HTML;
            }
        }
        // If not unary, add a closing tag.
        if (!$handled_as_void_tag && !Elements::is_a($name, Elements::VOID_TAG)) {
            $this->close_tag($ele);
        }
    }
    /**
     * Write a text node.
     *
     * @param \DOMText $ele The text node to write.
     */
    public function text($ele)
    {
        if (isset($ele->parent_node) && isset($ele->parent_node->tag_name) && Elements::is_a($ele->parent_node->local_name, Elements::TEXT_RAW)) {
            $this->wr($ele->data);
            return;
        }
        // FIXME: This probably needs some flags set.
        $this->wr($this->enc($ele->data));
    }
    public function cdata($ele)
    {
        // This encodes CDATA.
        $this->wr($ele->owner_document->save_xml($ele));
    }
    public function comment($ele)
    {
        // These produce identical output.
        // $this->wr('<!--')->wr($ele->data)->wr('-->');
        $this->wr($ele->owner_document->save_xml($ele));
    }
    public function processor_instruction($ele)
    {
        $this->wr('<?')->wr($ele->target)->wr(' ')->wr($ele->data)->wr('?>');
    }
    /**
     * Write the namespace attributes.
     *
     * @param \DOMNode $ele The element being written.
     */
    protected function namespace_attrs($ele)
    {
        if (!$this->xpath || $this->xpath->document !== $ele->owner_document) {
            $this->xpath = new \Domx_Path($ele->owner_document);
        }
        foreach ($this->xpath->query('namespace::*[not(.=../../namespace::*)]', $ele) as $ns_node) {
            if (!in_array($ns_node->node_value, $this->implicit_namespaces)) {
                $this->wr(' ')->wr($ns_node->node_name)->wr('="')->wr($ns_node->node_value)->wr('"');
            }
        }
    }
    /**
     * Write the opening tag.
     *
     * Tags for HTML, MathML, and SVG are in the local name. Otherwise, use the
     * qualified name (8.3).
     *
     * @param \DOMNode $ele The element being written.
     */
    protected function open_tag($ele)
    {
        $this->wr('<')->wr($this->traverser->is_local_element($ele) ? $ele->local_name : $ele->tag_name);
        $this->attrs($ele);
        $this->namespace_attrs($ele);
        if ($this->output_mode == static::IM_IN_HTML) {
            $this->wr('>');
        } else if ($ele->has_child_nodes()) {
            $this->wr('>');
        } else {
            $this->wr(' />');
        }
    }
    protected function attrs($ele)
    {
        // FIXME: Needs support for xml, xmlns, xlink, and namespaced elements.
        if (!$ele->has_attributes()) {
            return $this;
        }
        // TODO: Currently, this always writes name="value", and does not do
        // value-less attributes.
        $map = $ele->attributes;
        $len = $map->length;
        for ($i = 0; $i < $len; ++$i) {
            $node = $map->item($i);
            $val = $this->enc($node->value, true);
            // XXX: The spec says that we need to ensure that anything in
            // the XML, XMLNS, or XLink NS's should use the canonical
            // prefix. It seems that DOM does this for us already, but there
            // may be exceptions.
            $name = $node->node_name;
            // Special handling for attributes in SVG and MathML.
            // Using if/elseif instead of switch because it's faster in PHP.
            if ($this->output_mode == static::IM_IN_SVG) {
                $name = Elements::normalize_svg_attribute($name);
            } elseif ($this->output_mode == static::IM_IN_MATHML) {
                $name = Elements::normalize_math_ml_attribute($name);
            }
            $this->wr(' ')->wr($name);
            if (isset($val) && '' !== $val || $this->non_boolean_attribute($node)) {
                $this->wr('="')->wr($val)->wr('"');
            }
        }
    }
    protected function non_boolean_attribute(\Dom_Attr $attr)
    {
        $ele = $attr->owner_element;
        foreach ($this->non_boolean_attributes as $rule) {
            if (isset($rule['nodeNamespace']) && $rule['nodeNamespace'] !== $ele->namespace_uri) {
                continue;
            }
            if (isset($rule['attNamespace']) && $rule['attNamespace'] !== $attr->namespace_uri) {
                continue;
            }
            if (isset($rule['nodeName']) && !is_array($rule['nodeName']) && $rule['nodeName'] !== $ele->local_name) {
                continue;
            }
            if (isset($rule['nodeName']) && is_array($rule['nodeName']) && !in_array($ele->local_name, $rule['nodeName'], true)) {
                continue;
            }
            if (isset($rule['attrName']) && !is_array($rule['attrName']) && $rule['attrName'] !== $attr->local_name) {
                continue;
            }
            if (isset($rule['attrName']) && is_array($rule['attrName']) && !in_array($attr->local_name, $rule['attrName'], true)) {
                continue;
            }
            if (isset($rule['xpath'])) {
                $xp = $this->get_x_path($attr);
                if (isset($rule['prefixes'])) {
                    foreach ($rule['prefixes'] as $ns_prefix => $ns) {
                        $xp->register_namespace($ns_prefix, $ns);
                    }
                }
                if (!$xp->evaluate($rule['xpath'], $attr)) {
                    continue;
                }
            }
            return true;
        }
        return false;
    }
    private function get_x_path(\Dom_Node $node)
    {
        if (!$this->xpath) {
            $this->xpath = new \Domx_Path($node->owner_document);
        }
        return $this->xpath;
    }
    /**
     * Write the closing tag.
     *
     * Tags for HTML, MathML, and SVG are in the local name. Otherwise, use the
     * qualified name (8.3).
     *
     * @param \DOMNode $ele The element being written.
     */
    protected function close_tag($ele)
    {
        if ($this->output_mode == static::IM_IN_HTML || $ele->has_child_nodes()) {
            $this->wr('</')->wr($this->traverser->is_local_element($ele) ? $ele->local_name : $ele->tag_name)->wr('>');
        }
    }
    /**
     * Write to the output.
     *
     * @param string $text The string to put into the output
     *
     * @return $this
     */
    protected function wr($text)
    {
        fwrite($this->out, (string) $text);
        return $this;
    }
    /**
     * Write a new line character.
     *
     * @return $this
     */
    protected function nl()
    {
        fwrite($this->out, PHP_EOL);
        return $this;
    }
    /**
     * Encode text.
     *
     * When encode is set to false, the default value, the text passed in is
     * escaped per section 8.3 of the html5 spec. For details on how text is
     * escaped see the escape() method.
     *
     * When encoding is set to true the text is converted to named character
     * references where appropriate. Section 8.1.4 Character references of the
     * html5 spec refers to using named character references. This is useful for
     * characters that can't otherwise legally be used in the text.
     *
     * The named character references are listed in section 8.5.
     *
     * @see http://www.w3.org/TR/2013/CR-html5-20130806/syntax.html#named-character-references True encoding will turn all named character references into their entities.
     *      This includes such characters as +.# and many other common ones. By default
     *      encoding here will just escape &'<>".
     *
     *      Note, PHP 5.4+ has better html5 encoding.
     *
     * @todo Use the Entities class in php 5.3 to have html5 entities.
     *
     * @param string $text      Text to encode.
     * @param bool   $attribute True if we are encoding an attrubute, false otherwise.
     *
     * @return string The encoded text.
     */
    protected function enc($text, $attribute = false)
    {
        // Escape the text rather than convert to named character references.
        if (!$this->encode) {
            return $this->escape($text, $attribute);
        }
        // If we are in PHP 5.4+ we can use the native html5 entity functionality to
        // convert the named character references.
        if ($this->has_html5) {
            return htmlentities($text, ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES, 'UTF-8', false);
        }
        return strtr($text, HTML5Entities::$map);
    }
    /**
     * Escape test.
     *
     * According to the html5 spec section 8.3 Serializing HTML fragments, text
     * within tags that are not style, script, xmp, iframe, noembed, and noframes
     * need to be properly escaped.
     *
     * The & should be converted to &amp;, no breaking space unicode characters
     * converted to &nbsp;, when in attribute mode the " should be converted to
     * &quot;, and when not in attribute mode the < and > should be converted to
     * &lt; and &gt;.
     *
     * @see http://www.w3.org/TR/2013/CR-html5-20130806/syntax.html#escapingString
     *
     * @param string $text      Text to escape.
     * @param bool   $attribute True if we are escaping an attrubute, false otherwise.
     */
    protected function escape($text, $attribute = false)
    {
        // Not using htmlspecialchars because, while it does escaping, it doesn't
        // match the requirements of section 8.5. For example, it doesn't handle
        // non-breaking spaces.
        if ($attribute) {
            $replace = ['"' => '&quot;', '&' => '&amp;', " " => '&nbsp;'];
        } else {
            $replace = ['<' => '&lt;', '>' => '&gt;', '&' => '&amp;', " " => '&nbsp;'];
        }
        return strtr($text, $replace);
    }
}