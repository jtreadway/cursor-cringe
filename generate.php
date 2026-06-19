<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Calendar.php';
require_once __DIR__ . '/lib/CalendarLinter.php';
require_once __DIR__ . '/lib/VenuesIndex.php';

/**
 * Generate week JSON from cringe.com-style -noTag text files.
 *
 * With no file argument, processes every data/*-noTag.txt in date order.
 * Newer snapshots overwrite overlapping weekStart values.
 *
 * CLI:
 *   php generate.php
 *   php generate.php data/20260615-noTag.txt
 *
 * Browser:
 *   /generate.php
 *   /generate.php?file=data/20260615-noTag.txt
 */

function findLatestSourceFile(string $dataDir): ?string
{
    $files = findAllSourceFiles($dataDir);

    return $files === [] ? null : $files[array_key_last($files)];
}

/**
 * @return list<string>
 */
function findAllSourceFiles(string $dataDir): array
{
    if (!is_dir($dataDir)) {
        return [];
    }

    $files = glob($dataDir . '/*-noTag.txt') ?: [];
    if ($files === []) {
        $files = glob($dataDir . '/*-noTag') ?: [];
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * @return array{ok: bool, source: string, files: list<string>, venuesIndex: string, errors: list<string>, lintWarnings: list<array{code: string, message: string}>, weeks: list<array<string, mixed>>}
 */
function generateWeekJson(string $sourcePath, string $outputDir, string $venuesIndexPath): array
{
    $calendar = new Calendar($sourcePath);
    $weeks = $calendar->getWeeks();
    $errors = $calendar->getErrors();
    $lintWarnings = CalendarLinter::lintListings($calendar->getParsedListings());

    if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        throw new RuntimeException("Unable to create output directory: {$outputDir}");
    }

    $written = [];
    $now = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format(DateTimeInterface::ATOM);

    foreach ($weeks as $week) {
        $payload = [
            'weekStart' => $week['weekStart'],
            'sourceFile' => basename($sourcePath),
            'generatedAt' => $now,
            'days' => $week['days'],
        ];

        $outPath = $outputDir . '/' . $week['weekStart'] . '.json';
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON for week ' . $week['weekStart']);
        }

        if (file_put_contents($outPath, $json . "\n") === false) {
            throw new RuntimeException("Failed to write {$outPath}");
        }

        $written[] = $outPath;
    }

    VenuesIndex::updateFromWeeks($venuesIndexPath, $weeks, basename($sourcePath));

    return [
        'ok' => true,
        'source' => $sourcePath,
        'files' => $written,
        'venuesIndex' => $venuesIndexPath,
        'errors' => $errors,
        'lintWarnings' => $lintWarnings,
        'weeks' => $weeks,
    ];
}

/**
 * @return array{
 *     ok: bool,
 *     sources: list<string>,
 *     source: string,
 *     files: list<string>,
 *     venuesIndex: string,
 *     errors: list<string>,
 *     lintWarnings: list<array{code: string, message: string, source?: string}>,
 *     weeks: list<array<string, mixed>>
 * }
 */
function generateAllWeekJson(string $dataDir, string $outputDir, string $venuesIndexPath): array
{
    $sources = findAllSourceFiles($dataDir);

    if ($sources === []) {
        throw new RuntimeException('No source file found. Add data/YYYYMMDD-noTag.txt or pass a file path.');
    }

    $written = [];
    $errors = [];
    $lintWarnings = [];
    $weeks = [];

    foreach ($sources as $sourcePath) {
        $result = generateWeekJson($sourcePath, $outputDir, $venuesIndexPath);

        foreach ($result['files'] as $file) {
            $written[$file] = true;
        }

        foreach ($result['errors'] as $error) {
            $errors[] = basename($sourcePath) . ': ' . $error;
        }

        foreach ($result['lintWarnings'] as $warning) {
            $lintWarnings[] = $warning + ['source' => basename($sourcePath)];
        }

        $weeks = array_merge($weeks, $result['weeks']);
    }

    return [
        'ok' => true,
        'sources' => $sources,
        'source' => $sources[array_key_last($sources)],
        'files' => array_keys($written),
        'venuesIndex' => $venuesIndexPath,
        'errors' => $errors,
        'lintWarnings' => $lintWarnings,
        'weeks' => $weeks,
    ];
}

function respondJson(array $payload, int $status = 200): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

function respondBrowser(array $result): void
{
    header('Content-Type: text/html; charset=utf-8');
    $title = $result['ok'] ? 'Calendar JSON generated' : 'Calendar generation failed';
    $files = $result['files'] ?? [];
    $errors = $result['errors'] ?? [];
    $message = htmlspecialchars($result['message'] ?? '', ENT_QUOTES, 'UTF-8');
    $source = htmlspecialchars($result['source'] ?? '', ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . $title . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:48rem;margin:2rem auto;line-height:1.5}';
    echo 'code,pre{background:#f4f4f4;padding:.2rem .35rem;border-radius:.25rem}';
    echo '.error{color:#b00020}ul{padding-left:1.25rem}</style></head><body>';
    echo '<h1>' . $title . '</h1>';

    if (!$result['ok']) {
        echo '<p class="error">' . $message . '</p>';
        echo '</body></html>';
        return;
    }

    echo '<p>Source: <code>' . $source . '</code></p>';
    if (!empty($result['sources']) && count($result['sources']) > 1) {
        echo '<p>Processed ' . count($result['sources']) . ' source file(s):</p><ul>';
        foreach ($result['sources'] as $sourceFile) {
            $label = htmlspecialchars($sourceFile, ENT_QUOTES, 'UTF-8');
            echo '<li><code>' . $label . '</code></li>';
        }
        echo '</ul>';
    }
    echo '<p>Generated ' . count($files) . ' week file(s):</p><ul>';
    foreach ($files as $file) {
        $href = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        echo '<li><a href="' . $href . '"><code>' . $href . '</code></a></li>';
    }
    echo '</ul>';

    if (!empty($result['venuesIndex'])) {
        $venuesHref = htmlspecialchars((string) $result['venuesIndex'], ENT_QUOTES, 'UTF-8');
        echo '<p>Updated venues index: <a href="' . $venuesHref . '"><code>' . $venuesHref . '</code></a></p>';
    }

    if ($errors !== []) {
        echo '<h2>Parser warnings</h2><ul class="error">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    }

    $lintWarnings = $result['lintWarnings'] ?? [];
    if ($lintWarnings !== []) {
        echo '<h2>Lint warnings</h2><p>These may be intentional duplicate listings — review the source file.</p><ul class="error">';
        foreach ($lintWarnings as $warning) {
            $code = htmlspecialchars($warning['code'], ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars($warning['message'], ENT_QUOTES, 'UTF-8');
            $sourceLabel = isset($warning['source'])
                ? '<code>' . htmlspecialchars((string) $warning['source'], ENT_QUOTES, 'UTF-8') . '</code> — '
                : '';
            echo '<li>' . $sourceLabel . '<code>' . $code . '</code> — ' . $message . '</li>';
        }
        echo '</ul>';
    }

    echo '<p>Verify output against golden PHP partials with <a href="verify.php">verify.php</a> or <code>php verify.php</code>.</p>';
    echo '<p>Run again with <code>?file=data/YYYYMMDD-noTag.txt</code> or <code>?format=json</code> for raw JSON.</p>';
    echo '</body></html>';
}

function main(array $argv): int
{
    $baseDir = __DIR__;
    $dataDir = $baseDir . '/data';
    $outputDir = $baseDir . '/weeks';
    $venuesIndexPath = $baseDir . '/venues.json';

    $sourceArg = null;
    if (PHP_SAPI === 'cli') {
        $sourceArg = $argv[1] ?? null;
    } else {
        $sourceArg = $_GET['file'] ?? null;
    }

    if ($sourceArg === null || $sourceArg === '') {
        try {
            $result = generateAllWeekJson($dataDir, $outputDir, $venuesIndexPath);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $message . PHP_EOL);
                fwrite(STDERR, "Usage: php generate.php [data/YYYYMMDD-noTag.txt]\n");
                return 1;
            }

            respondBrowser(['ok' => false, 'message' => $message]);
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

            respondBrowser(['ok' => false, 'message' => $message]);
            return 1;
        }

        try {
            $result = generateWeekJson($sourcePath, $outputDir, $venuesIndexPath);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $message . PHP_EOL);
                return 1;
            }

            respondBrowser(['ok' => false, 'message' => $message]);
            return 1;
        }
    }

    if (PHP_SAPI === 'cli') {
        if (!empty($result['sources']) && count($result['sources']) > 1) {
            echo 'Generated ' . count($result['files']) . ' week file(s) from ' . count($result['sources']) . " source file(s)\n";
            foreach ($result['sources'] as $sourceFile) {
                echo "  {$sourceFile}\n";
            }
        } else {
            echo "Generated " . count($result['files']) . " week file(s) from {$result['source']}\n";
        }
        foreach ($result['files'] as $file) {
            echo "  {$file}\n";
        }
        echo "  {$result['venuesIndex']}\n";
        if ($result['errors'] !== []) {
            fwrite(STDERR, "\nParser warnings:\n");
            foreach ($result['errors'] as $error) {
                fwrite(STDERR, "  {$error}\n");
            }
        }
        if ($result['lintWarnings'] !== []) {
            fwrite(STDERR, "\nLint warnings (may be intentional):\n");
            foreach ($result['lintWarnings'] as $warning) {
                $sourceLabel = isset($warning['source']) ? "[{$warning['source']}] " : '';
                fwrite(STDERR, "  {$sourceLabel}[{$warning['code']}] {$warning['message']}\n");
            }
        }
        return 0;
    }

    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        respondJson($result);
        return 0;
    }

    respondBrowser($result);
    return 0;
}

exit(main($argv));
