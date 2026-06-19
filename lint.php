<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/CalendarLinter.php';

/**
 * Lint a cringe.com-style -noTag source file for suspicious venue/event patterns.
 *
 * Warnings only — intentional duplicate listings are allowed. Use --strict to exit 1
 * when any warning is found (useful for CI if you want to gate on a clean lint).
 *
 * CLI:
 *   php lint.php
 *   php lint.php data/20260615-noTag.txt
 *   php lint.php --strict
 *
 * Browser:
 *   /lint.php
 *   /lint.php?file=data/20260615-noTag.txt
 */

function findLatestSourceFile(string $dataDir): ?string
{
    if (!is_dir($dataDir)) {
        return null;
    }

    $files = glob($dataDir . '/*-noTag.txt') ?: [];
    if ($files === []) {
        $files = glob($dataDir . '/*-noTag') ?: [];
    }

    if ($files === []) {
        return null;
    }

    rsort($files, SORT_STRING);

    return $files[0];
}

function respondBrowser(string $sourcePath, array $warnings): void
{
    header('Content-Type: text/html; charset=utf-8');
    $title = $warnings === [] ? 'Calendar lint — no warnings' : 'Calendar lint — warnings';
    $source = htmlspecialchars($sourcePath, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:48rem;margin:2rem auto;line-height:1.5}';
    echo 'code{background:#f4f4f4;padding:.2rem .35rem;border-radius:.25rem}';
    echo '.warn{color:#8a4b00}ul{padding-left:1.25rem}</style></head><body>';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>Source: <code>' . $source . '</code></p>';

    if ($warnings === []) {
        echo '<p>No suspicious venue/event patterns found.</p>';
    } else {
        echo '<p class="warn">' . count($warnings) . ' warning(s). These may be intentional — review before changing source data.</p>';
        echo '<ul>';
        foreach ($warnings as $warning) {
            $code = htmlspecialchars($warning['code'], ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars($warning['message'], ENT_QUOTES, 'UTF-8');
            echo '<li><code>' . $code . '</code> — ' . $message . '</li>';
        }
        echo '</ul>';
    }

    echo '<p>Run again with <code>?file=data/YYYYMMDD-noTag.txt</code> or <code>php lint.php [file] [--strict]</code>.</p>';
    echo '</body></html>';
}

function main(array $argv): int
{
    $baseDir = __DIR__;
    $dataDir = $baseDir . '/data';
    $strict = in_array('--strict', $argv, true);
    $sourceArg = null;

    foreach ($argv as $index => $arg) {
        if ($index === 0 || $arg === '--strict') {
            continue;
        }

        $sourceArg = $arg;
        break;
    }

    if (PHP_SAPI !== 'cli') {
        $sourceArg = $_GET['file'] ?? null;
        $strict = isset($_GET['strict']);
    }

    if ($sourceArg === null || $sourceArg === '') {
        $sourcePath = findLatestSourceFile($dataDir);
        if ($sourcePath === null) {
            $message = 'No source file found. Add data/YYYYMMDD-noTag.txt or pass a file path.';
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $message . PHP_EOL);

                return 1;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="en"><body><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';

            return 1;
        }
    } else {
        $sourcePath = str_starts_with($sourceArg, '/')
            ? $sourceArg
            : $baseDir . '/' . ltrim($sourceArg, '/');

        if (!is_readable($sourcePath)) {
            $message = "Cannot read source file: {$sourceArg}";
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $message . PHP_EOL);

                return 1;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="en"><body><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';

            return 1;
        }
    }

    try {
        $warnings = CalendarLinter::lintFile($sourcePath);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);

            return 1;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><body><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';

        return 1;
    }

    if (PHP_SAPI === 'cli') {
        if ($warnings === []) {
            echo "No warnings for {$sourcePath}\n";

            return 0;
        }

        echo count($warnings) . " warning(s) for {$sourcePath}\n";
        foreach ($warnings as $warning) {
            echo "  [{$warning['code']}] {$warning['message']}\n";
        }

        return $strict ? 1 : 0;
    }

    respondBrowser($sourcePath, $warnings);

    return $strict && $warnings !== [] ? 1 : 0;
}

exit(main($argv));
