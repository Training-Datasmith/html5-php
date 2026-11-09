<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$iterations = isset($argv[1]) ? $argv[1] : 100;

$html5 = new Masterminds\HTML5();
$content = file_get_contents(__DIR__ . '/example.html');
$dom = $html5->load_html($content);

$samples = [];
for ($i = 0; $i < $iterations; ++$i) {
    $t = microtime(true);
    $dom = $html5->load_html($content);
    $samples[] = microtime(true) - $t;
}
$time = array_sum($samples) / count($samples);
echo 'Loading: ' . ($time * 1000) . "\n";

$samples = [];
for ($i = 0; $i < $iterations; ++$i) {
    $t = microtime(true);
    $html5->save_html($dom);
    $samples[] = microtime(true) - $t;
}
$time = array_sum($samples) / count($samples);
echo 'Writing: ' . ($time * 1000) . "\n";

exit(0);
