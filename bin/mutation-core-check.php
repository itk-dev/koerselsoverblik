#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Fails when a covered mutant escaped in the code that makes the figures.
 *
 * Infection has one threshold for each run. This script adds a covered MSI gate of 100 % for
 * the directories below. Infection counts timed-out and errored mutants as detected, so the
 * covered MSI is 100 % when no mutant escaped. See "Mutation testing" in docs/plan.md.
 *
 * Usage: bin/mutation-core-check.php <INFECTION_JSON_LOG>
 */

const CORE_DIRECTORIES = ['src/Import/', 'src/Report/'];

$logPath = $argv[1] ?? '';
$log = is_file($logPath) ? json_decode((string) file_get_contents($logPath), true) : null;
if (!is_array($log) || !is_array($log['escaped'] ?? null)) {
    fwrite(\STDERR, sprintf("Cannot read the escaped mutants in the Infection JSON log \"%s\".\n", $logPath));
    exit(2);
}

$projectDir = dirname(__DIR__).'/';
$escaped = [];
foreach ($log['escaped'] as $result) {
    $mutator = is_array($result) && is_array($result['mutator'] ?? null) ? $result['mutator'] : [];
    $file = is_string($mutator['originalFilePath'] ?? null) ? $mutator['originalFilePath'] : '';
    $relativeFile = str_starts_with($file, $projectDir) ? substr($file, strlen($projectDir)) : $file;

    foreach (CORE_DIRECTORIES as $directory) {
        if (str_starts_with($relativeFile, $directory)) {
            $line = is_int($mutator['originalStartLine'] ?? null) ? $mutator['originalStartLine'] : 0;
            $name = is_string($mutator['mutatorName'] ?? null) ? $mutator['mutatorName'] : '';
            $escaped[] = sprintf('%s:%d %s', $relativeFile, $line, $name);
        }
    }
}

if ([] !== $escaped) {
    fwrite(\STDERR, sprintf(
        "The covered MSI must be 100 %% in %s. Escaped mutants: %d\n  %s\n",
        implode(' and ', CORE_DIRECTORIES),
        count($escaped),
        implode("\n  ", $escaped),
    ));
    exit(1);
}

echo sprintf("No mutants escaped in %s.\n", implode(' and ', CORE_DIRECTORIES));
