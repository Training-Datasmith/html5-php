<?php

declare(strict_types=1);

/**
 * Fail if cluster packages from the Training-Datasmith index are not installed
 * at the expected SHA and root alias (installed.php) and source.reference (installed.json).
 */

$root = dirname(__DIR__);
$installedPhp = $root . '/vendor/composer/installed.php';
$installedJson = $root . '/vendor/composer/installed.json';

$expected = [
    'nikic/php-parser' => [
        'alias' => '5.7.0',
        'reference' => '50f0d9c9d0e3cff1163c959c50aaaaa4a7115f08',
    ],
];

/**
 * @return list<string>
 */
function assert_cluster_pins(array $expected, array $versions, array $jsonPackages): array
{
    $errors = [];

    $installedByName = [];
    foreach ($jsonPackages as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $installedByName[$package['name']] = strtolower((string) $package['source']['reference']);
    }

    foreach ($expected as $name => $want) {
        if (!isset($versions[$name])) {
            $errors[] = sprintf('check-cluster-pins: %s is not installed.', $name);
            continue;
        }

        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            $errors[] = sprintf(
                'check-cluster-pins: %s alias mismatch (expected %s in aliases %s).',
                $name,
                $want['alias'],
                json_encode($aliases)
            );
        }

        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $errors[] = sprintf('check-cluster-pins: %s has no reference in installed.php.', $name);
        } elseif (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                'check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).',
                $name,
                $want['reference'],
                $reference
            );
        }

        if (!isset($installedByName[$name])) {
            $errors[] = sprintf('check-cluster-pins: %s missing from installed.json with source.reference.', $name);
            continue;
        }

        if (!hash_equals(strtolower($want['reference']), $installedByName[$name])) {
            $errors[] = sprintf(
                'check-cluster-pins: %s installed.json source.reference mismatch (expected %s, got %s).',
                $name,
                $want['reference'],
                $installedByName[$name]
            );
        }
    }

    return $errors;
}

if (!is_readable($installedPhp)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

$rawJson = file_get_contents($installedJson);
if ($rawJson === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
    exit(1);
}

/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $data */
$data = json_decode($rawJson, true);
if (!is_array($data)) {
    fwrite(STDERR, "check-cluster-pins: invalid installed.json.\n");
    exit(1);
}

$jsonPackages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);

$errors = assert_cluster_pins($expected, $versions, $jsonPackages);
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

// In-memory wrong-SHA demo: installed.php reference
$mutatedVersions = $versions;
if (isset($mutatedVersions['nikic/php-parser'])) {
    $mutatedVersions['nikic/php-parser']['reference'] = str_repeat('0', 40);
}
$demoPhpErrors = assert_cluster_pins($expected, $mutatedVersions, $jsonPackages);
$phpDemoFailed = false;
foreach ($demoPhpErrors as $error) {
    if (stripos($error, 'installed.php') !== false && stripos($error, 'reference') !== false) {
        $phpDemoFailed = true;
        break;
    }
}
if (!$phpDemoFailed) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.php did not fail as expected.\n");
    exit(1);
}

// In-memory wrong-SHA demo: installed.json source.reference
$mutatedJsonPackages = $jsonPackages;
foreach ($mutatedJsonPackages as $i => $package) {
    if (($package['name'] ?? '') === 'nikic/php-parser') {
        $mutatedJsonPackages[$i]['source']['reference'] = str_repeat('f', 40);
    }
}
$demoJsonErrors = assert_cluster_pins($expected, $versions, $mutatedJsonPackages);
$jsonDemoFailed = false;
foreach ($demoJsonErrors as $error) {
    if (stripos($error, 'installed.json') !== false && stripos($error, 'source.reference') !== false) {
        $jsonDemoFailed = true;
        break;
    }
}
if (!$jsonDemoFailed) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.json did not fail as expected.\n");
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
