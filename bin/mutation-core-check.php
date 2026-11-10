#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Fails when a covered mutant escaped, or was skipped, in the code that makes the figures.
 *
 * Infection has one threshold for each run. This script adds a covered MSI gate of 100 % for
 * the directories below. Infection counts timed-out and errored mutants as detected, so the
 * covered MSI is 100 % when no mutant escaped.
 *
 * Infection skips a mutant when all its tests took more than the timeout in the initial run. A
 * skipped mutant is not tested, so it also fails the gate. The JSON log has only the number of
 * skipped mutants, so the script reads the list from the text log. See "Tests" in README.md.
 *
 * Usage: bin/mutation-core-check.php <INFECTION_JSON_LOG> <INFECTION_TEXT_LOG>
 */

const CORE_DIRECTORIES = ['src/Import/', 'src/Report/'];

$projectDir = dirname(__DIR__).'/';

/**
 * Gives the path relative to the project, if the file is in a core directory.
 */
function corePath(string $file, string $projectDir): ?string
{
    $relativeFile = str_starts_with($file, $projectDir) ? substr($file, strlen($projectDir)) : $file;
    foreach (CORE_DIRECTORIES as $directory) {
        if (str_starts_with($relativeFile, $directory)) {
            return $relativeFile;
        }
    }

    return null;
}

$logPath = $argv[1] ?? '';
$log = is_file($logPath) ? json_decode((string) file_get_contents($logPath), true) : null;
if (!is_array($log) || !is_array($log['escaped'] ?? null)) {
    fwrite(\STDERR, sprintf("Cannot read the escaped mutants in the Infection JSON log \"%s\".\n", $logPath));
    exit(2);
}

$textLogPath = $argv[2] ?? '';
$textLog = is_file($textLogPath) ? file_get_contents($textLogPath) : false;
if (false === $textLog) {
    fwrite(\STDERR, sprintf("Cannot read the Infection text log \"%s\".\n", $textLogPath));
    exit(2);
}

$escaped = [];
foreach ($log['escaped'] as $result) {
    $mutator = is_array($result) && is_array($result['mutator'] ?? null) ? $result['mutator'] : [];
    $file = corePath(is_string($mutator['originalFilePath'] ?? null) ? $mutator['originalFilePath'] : '', $projectDir);
    if (null !== $file) {
        $line = is_int($mutator['originalStartLine'] ?? null) ? $mutator['originalStartLine'] : 0;
        $name = is_string($mutator['mutatorName'] ?? null) ? $mutator['mutatorName'] : '';
        $escaped[] = sprintf('%s:%d %s', $file, $line, $name);
    }
}

// The text log lists each skipped mutant as "1) /app/src/File.php:84    [M] InstanceOf_ [ID] ...".
$skipped = [];
$skippedSection = preg_split('/^Skipped mutants:$/m', $textLog)[1] ?? '';
$skippedSection = preg_split('/^\w[\w ]* mutants:$/m', $skippedSection)[0] ?? '';
preg_match_all('/^\d+\) (\S+):(\d+)\s+\[M\] (\w+)/m', $skippedSection, $matches, \PREG_SET_ORDER);
foreach ($matches as [, $path, $line, $name]) {
    $file = corePath($path, $projectDir);
    if (null !== $file) {
        $skipped[] = sprintf('%s:%s %s', $file, $line, $name);
    }
}

$stats = is_array($log['stats'] ?? null) ? $log['stats'] : [];
if (($stats['skippedCount'] ?? 0) > 0 && [] === $matches) {
    fwrite(\STDERR, "The JSON log has skipped mutants, but the text log lists none. Check the format of the text log.\n");
    exit(2);
}

$failed = false;
foreach (['Escaped' => $escaped, 'Skipped' => $skipped] as $label => $mutants) {
    if ([] !== $mutants) {
        fwrite(\STDERR, sprintf(
            "The covered MSI must be 100 %% in %s. %s mutants: %d\n  %s\n",
            implode(' and ', CORE_DIRECTORIES),
            $label,
            count($mutants),
            implode("\n  ", $mutants),
        ));
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

echo sprintf("No mutants escaped or were skipped in %s.\n", implode(' and ', CORE_DIRECTORIES));
