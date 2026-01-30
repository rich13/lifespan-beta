#!/usr/bin/env php
<?php
/**
 * Parse a JUnit XML file (from Pest/PHPUnit --log-junit) and print the slowest tests.
 *
 * Usage:
 *   php scripts/slowest-tests-from-junit.php [path/to/junit.xml] [count]
 *
 * Default: path = storage/logs/pest-junit-*.xml (latest), count = 30
 */

$junitPath = $argv[1] ?? null;
$limit = isset($argv[2]) ? (int) $argv[2] : 30;

if ($junitPath === null || $junitPath === '-h' || $junitPath === '--help') {
    echo "Usage: php scripts/slowest-tests-from-junit.php [junit.xml] [count]\n";
    echo "  junit.xml  Path to JUnit XML (e.g. from pest --log-junit=...). Default: latest pest-junit-*.xml in storage/logs\n";
    echo "  count      Number of slowest tests to show (default: 30)\n";
    exit($junitPath === null ? 1 : 0);
}

if (!file_exists($junitPath)) {
    // Try storage/logs if path is relative and we're in repo root
    $candidates = [__DIR__ . '/../storage/logs/' . basename($junitPath), $junitPath];
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $junitPath = $c;
            break;
        }
    }
    if (!file_exists($junitPath)) {
        fwrite(STDERR, "File not found: {$junitPath}\n");
        exit(1);
    }
}

$xml = @simplexml_load_file($junitPath);
if ($xml === false) {
    fwrite(STDERR, "Invalid or unreadable XML: {$junitPath}\n");
    exit(1);
}

$cases = [];
foreach ($xml->xpath('//testcase') ?: [] as $tc) {
    $name = (string) ($tc['name'] ?? '');
    $class = (string) ($tc['class'] ?? $tc['classname'] ?? '');
    $time = (float) ($tc['time'] ?? 0);
    $fullName = $class !== '' ? $class . '::' . $name : $name;
    $cases[] = ['name' => $fullName, 'time' => $time];
}

usort($cases, static function ($a, $b) {
    return $b['time'] <=> $a['time'];
});

$cases = array_slice($cases, 0, $limit);

if (count($cases) === 0) {
    echo "No testcase entries with timing found in the JUnit XML.\n";
    exit(0);
}

$maxTime = $cases[0]['time'];
$width = strlen(number_format($maxTime, 2));

echo "\nSlowest {$limit} tests (by execution time):\n";
echo str_repeat('-', 80) . "\n";
foreach ($cases as $i => $c) {
    printf("  %" . $width . "2fs  %s\n", $c['time'], $c['name']);
}
echo str_repeat('-', 80) . "\n";
