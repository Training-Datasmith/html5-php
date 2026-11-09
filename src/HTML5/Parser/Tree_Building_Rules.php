<?php

declare (strict_types=1);
namespace Masterminds\HTML5\Parser;

/**
 * Handles special-case rules for the DOM tree builder.
 *
 * Many tags have special rules that need to be accomodated on an
 * individual basis. This class handles those rules.
 *
 * See section 8.1.2.4 of the spec.
 *
 * @todo - colgroup and col special behaviors
 *       - body and head special behaviors
 */
class Tree_Building_Rules
{
    protected static $tags = ['li' => 1, 'dd' => 1, 'dt' => 1, 'rt' => 1, 'rp' => 1, 'tr' => 1, 'th' => 1, 'td' => 1, 'thead' => 1, 'tfoot' => 1, 'tbody' => 1, 'table' => 1, 'optgroup' => 1, 'option' => 1];
    /**
     * Returns true if the given tagname has special processing rules.
     */
    public function has_rules($tagname)
    {
        return isset(static::$tags[$tagname]);
    }
    /**
     * Evaluate the rule for the current tag name.
     *
     * This may modify the existing DOM.
     *
     * @return \DOMElement The new Current DOM element.
     */
    public function evaluate($new, $current)
    {
        switch ($new->tagName) {
            case 'li':
                return $this->handle_li($new, $current);
            case 'dt':
            case 'dd':
                return $this->handle_dt($new, $current);
            case 'rt':
            case 'rp':
                return $this->handle_rt($new, $current);
            case 'optgroup':
                return $this->close_if_current_matches($new, $current, ['optgroup']);
            case 'option':
                return $this->close_if_current_matches($new, $current, ['option']);
            case 'tr':
                return $this->close_if_current_matches($new, $current, ['tr']);
            case 'td':
            case 'th':
                return $this->close_if_current_matches($new, $current, ['th', 'td']);
            case 'tbody':
            case 'thead':
            case 'tfoot':
            case 'table':
                // Spec isn't explicit about this, but it's necessary.
                return $this->close_if_current_matches($new, $current, ['thead', 'tfoot', 'tbody']);
        }
        return $current;
    }
    protected function handle_li($ele, $current)
    {
        return $this->close_if_current_matches($ele, $current, ['li']);
    }
    protected function handle_dt($ele, $current)
    {
        return $this->close_if_current_matches($ele, $current, ['dt', 'dd']);
    }
    protected function handle_rt($ele, $current)
    {
        return $this->close_if_current_matches($ele, $current, ['rt', 'rp']);
    }
    protected function close_if_current_matches($ele, $current, $match)
    {
        if (in_array($current->tagName, $match, true)) {
            $current->parentNode->appendChild($ele);
        } else {
            $current->appendChild($ele);
        }
        return $ele;
    }
}