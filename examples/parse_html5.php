<?php

declare(strict_types=1);

/**
 * Example: parse HTML5 into a DOMDocument and serialize back.
 *
 * Run from the html5-php project root:
 *   php examples/parse_html5.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Masterminds\HTML5;

$html5 = new HTML5();

// --- Parse a full HTML5 document ---
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><title>Example</title></head>
<body>
  <h1>Hello HTML5</h1>
  <p>This is a <strong>test</strong> of the HTML5 parser.</p>
  <article>
    <p>Semantic elements work correctly.</p>
  </article>
</body>
</html>
HTML;

$dom = $html5->load_html($html);

// Use standard DOMDocument / DOMXPath APIs
$xpath = new DOMXPath($dom);
$h1    = $xpath->query('//h1')->item(0);
echo "H1 text: " . $h1->textContent . "\n";

// --- Parse a fragment ---
$fragment = $html5->load_html_fragment('<p>Fragment <em>text</em>.</p>');
echo "Fragment node count: " . $fragment->childNodes->length . "\n";

// --- Serialize back to HTML5 string ---
$output = $html5->save_html($dom);
echo "Serialized length: " . strlen($output) . " bytes\n";

// --- Check for parse errors ---
if ($html5->has_errors()) {
    echo "Parse errors:\n";
    foreach ($html5->get_errors() as $error) {
        echo "  - " . $error . "\n";
    }
} else {
    echo "No parse errors.\n";
}
