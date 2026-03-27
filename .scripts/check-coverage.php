#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Validates that Clover coverage meets the minimum threshold (95%).
 * Run after: composer test-coverage (generates coverage.xml).
 *
 * Usage: php .scripts/check-coverage.php [min-percent]
 * Default min: 95
 */
$minPercent = isset($argv[1]) ? (float) $argv[1] : 95.0;
$file       = __DIR__ . '/../coverage.xml';

if (!file_exists($file)) {
    fwrite(\STDERR, "ERROR: coverage.xml not found. Run: composer test-coverage\n");
    exit(1);
}

$coverage = simplexml_load_file($file);
if ($coverage === false) {
    fwrite(\STDERR, "ERROR: Could not read coverage.xml\n");
    exit(1);
}

$metrics         = $coverage->project->metrics;
$elements        = (float) $metrics['elements'];
$coveredElements = (float) $metrics['coveredelements'];

if ($elements === 0.0) {
    echo "No elements to cover.\n";
    exit(0);
}

$percentage = ($coveredElements / $elements) * 100;
echo sprintf("Coverage: %s/%s (%.2f%%)\n", (int) $coveredElements, (int) $elements, $percentage);

if ($percentage < $minPercent) {
    fwrite(\STDERR, sprintf("ERROR: Line coverage must be at least %.0f%%. Current: %.2f%%\n", $minPercent, $percentage));
    exit(1);
}

echo sprintf("✅ %.0f%% line coverage confirmed\n", $minPercent);
exit(0);
