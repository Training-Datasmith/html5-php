<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/** @var list<string> */
$scanPaths = [
    $root . '/src',
    $root . '/test',
    $root . '/bin',
    $root . '/examples',
    $root . '/example.php',
];

/** @var list<string> */
$forbiddenPatterns = [
    'InstructionProcessor',
    'CharacterReference',
    'DOMTreeBuilder',
    'Dom_Tree_Builder',
    'EventHandler',
    'FileInputStream',
    'InputStream',
    'ParseError',
    'StringInputStream',
    'TreeBuildingRules',
    'OutputRules',
    'RulesInterface',
    'ElementsTest',
    'Html5Test',
    'CharacterReferenceTest',
    'DOMTreeBuilderTest',
    'EventStack',
    'EventStackError',
    'InstructionProcessorMock',
    'ScannerTest',
    'TokenizerTest',
    'TreeBuildingRulesTest',
    'UTF8UtilsTest',
    'OutputRulesTest',
    'TraverserTest',
    'loadHTMLFragment',
    'loadHTMLFile',
    'getOptions',
    'hasErrors',
    'getErrors',
    'isHtml5Element',
    'is_html5element',
    'isMathMLElement',
    'isSvgElement',
    'isElement',
    'normalizeSvgElement',
    'normalizeSvgAttribute',
    'normalizeMathMlAttribute',
    'lookupName',
    'lookupHex',
    'lookupDecimal',
    'implicitNamespaces',
    'xmlNamespaces',
    'Elements::isA',
    'function isA',
    'html5->loadHTML',
    'html5->saveHTML',
    'function loadHTML',
    'function saveHTML',
    '\\Dom_Element',
    '\\Dom_Document',
    '\\Dom_Document_Fragment',
    '\\Dom_Node_List',
    '\\Dom_Attr',
    '\\Dom_Node',
    '\\Dom_Implementation',
    '\\Dom_Exception',
    '\\Dom_Character_Data',
    '\\Domx_Path',
    'Return_Type_Will_Change',
    '->append_child',
    '->create_element',
    '->create_element_ns',
    '->create_document',
    '->create_document_fragment',
    '->create_document_type',
    '->create_comment',
    '->create_text_node',
    '->create_cdata_section',
    '->create_processing_instruction',
    '->import_node',
    '->set_attribute',
    '->set_attribute_ns',
    '->set_id_attribute',
    '->has_attributes',
    '->has_child_nodes',
    '->load_xml',
    '->save_xml',
    '->tag_name',
    '->local_name',
    '->namespace_uri',
    '->node_name',
    '->node_type',
    '->node_value',
    '->owner_document',
    '->owner_element',
    '->parent_node',
    '->child_nodes',
    '->document_element',
    'assertRegExp(',
];

$failures = [];

/**
 * @return iterable<string>
 */
function php_files_to_scan(array $scanPaths): iterable
{
    foreach ($scanPaths as $path) {
        if (is_file($path) && str_ends_with($path, '.php')) {
            yield $path;
            continue;
        }
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getPathname(), '.php')) {
                yield $file->getPathname();
            }
        }
    }
}

foreach (php_files_to_scan($scanPaths) as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        $failures[] = "Could not read {$file}";
        continue;
    }

    $relative = str_starts_with($file, $root) ? substr($file, strlen($root) + 1) : $file;

    foreach ($forbiddenPatterns as $pattern) {
        $matched = false;
        if (str_starts_with($pattern, '->') || str_contains($pattern, '::')) {
            if ($pattern === '->tag_name') {
                $matched = (bool) preg_match('/->tag_name(?!\\()/s', $contents);
            } else {
                $matched = strpos($contents, $pattern) !== false;
            }
        } elseif (str_contains($pattern, '(')) {
            $matched = strpos($contents, $pattern) !== false;
        } else {
            $quoted = preg_quote($pattern, '/');
            $matched = (bool) preg_match('/(?<![A-Za-z0-9_])' . $quoted . '(?![A-Za-z0-9_])/', $contents);
        }
        if (!$matched) {
            continue;
        }
        if ($pattern === 'Html5Test') {
            $stripped = str_replace('Html5Test.html', '', $contents);
            if (preg_match('/\bHtml5Test\b/', $stripped)) {
                $failures[] = "{$relative}: forbidden '{$pattern}'";
            }
            continue;
        }
        $failures[] = "{$relative}: forbidden '{$pattern}'";
    }

    if (preg_match('/\bclass\s+TestCase\b/', $contents) && strpos($contents, 'PHPUnit\\Framework\\TestCase') === false) {
        $failures[] = "{$relative}: repo-owned class TestCase (expected Test_Case)";
    }
    if (preg_match('/extends\s+TestCase\b/', $contents) && !preg_match('/extends\s+BaseTestCase\b/', $contents)) {
        if (!preg_match('/class\s+Test_Case\b/', $contents)) {
            $failures[] = "{$relative}: extends TestCase without BaseTestCase";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "check-rename-complete: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-rename-complete: no pre-rename repo-owned names remain.\n");
exit(0);
