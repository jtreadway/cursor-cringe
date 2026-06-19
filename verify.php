<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/WeekRenderer.php';

/**
 * Regression-test JSON week files against golden PHP partials from the live calendar pipeline.
 *
 * CLI:
 *   php verify.php
 *   php verify.php --verbose
 *   php verify.php weeks/20260615.json
 *
 * Browser:
 *   /verify.php
 *   /verify.php?verbose=1
 */

/**
 * @return list<string>
 */
function eventLinks(string $html): array
{
    preg_match_all('/<b>[A-Za-z]{3} \d+:<\/b>.*<a href="([^"]+)"/i', $html, $matches);

    $urls = $matches[1];
    sort($urls);

    return $urls;
}

/**
 * @return list<array{line: int, rendered: string, expected: string}>
 */
function diffLines(string $rendered, string $expected): array
{
    $renderedLines = explode("\n", $rendered);
    $expectedLines = explode("\n", $expected);
    $max = max(count($renderedLines), count($expectedLines));
    $diffs = [];

    for ($i = 0; $i < $max; $i++) {
        $left = $renderedLines[$i] ?? null;
        $right = $expectedLines[$i] ?? null;

        if ($left !== $right) {
            $diffs[] = [
                'line' => $i + 1,
                'rendered' => $left ?? '(missing line)',
                'expected' => $right ?? '(missing line)',
            ];
        }
    }

    return $diffs;
}

/**
 * @return list<string>
 */
function resolveWeekFiles(array $argv): array
{
    $files = [];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--verbose' || $arg === '-v') {
            continue;
        }

        $path = str_starts_with($arg, '/')
            ? $arg
            : __DIR__ . '/' . ltrim($arg, '/');

        if (!is_readable($path)) {
            throw new InvalidArgumentException("Cannot read week file: {$arg}");
        }

        $files[] = $path;
    }

    if ($files !== []) {
        return $files;
    }

    $jsonFiles = glob(__DIR__ . '/weeks/*.json') ?: [];
    sort($jsonFiles, SORT_STRING);

    return $jsonFiles;
}

function isVerbose(array $argv): bool
{
    if (PHP_SAPI !== 'cli') {
        return isset($_GET['verbose']) && $_GET['verbose'] !== '0';
    }

    return in_array('--verbose', $argv, true) || in_array('-v', $argv, true);
}

/**
 * @return array{
 *   ok: bool,
 *   checked: int,
 *   passed: int,
 *   results: list<array<string, mixed>>
 * }
 */
function verifyWeeks(array $jsonFiles, bool $verbose): array
{
    $results = [];
    $passed = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($jsonFiles as $jsonPath) {
        $weekId = basename($jsonPath, '.json');
        $goldenPath = dirname($jsonPath) . '/' . $weekId . '.php';

        $result = [
            'week' => $weekId,
            'json' => $jsonPath,
            'golden' => $goldenPath,
            'ok' => false,
            'skipped' => false,
            'message' => '',
            'lineDiffs' => 0,
            'eventLinks' => [
                'rendered' => 0,
                'expected' => 0,
                'match' => false,
            ],
            'diffs' => [],
        ];

        if (!is_readable($goldenPath)) {
            $result['ok'] = true;
            $result['skipped'] = true;
            $result['message'] = 'skipped (no golden partial)';
            $skipped++;
            $results[] = $result;
            continue;
        }

        $rendered = WeekRenderer::renderWeek(WeekRenderer::loadWeek($jsonPath));
        $expected = file_get_contents($goldenPath);
        if ($expected === false) {
            $result['message'] = "Unable to read golden PHP partial: {$goldenPath}";
            $results[] = $result;
            continue;
        }

        $renderedLinks = eventLinks($rendered);
        $expectedLinks = eventLinks($expected);
        $result['eventLinks'] = [
            'rendered' => count($renderedLinks),
            'expected' => count($expectedLinks),
            'match' => $renderedLinks === $expectedLinks,
        ];

        if ($rendered === $expected) {
            $result['ok'] = true;
            $result['message'] = 'byte-identical';
            $passed++;
            $results[] = $result;
            continue;
        }

        $diffs = diffLines($rendered, $expected);
        $result['lineDiffs'] = count($diffs);
        $result['diffs'] = $verbose ? $diffs : array_slice($diffs, 0, 5);
        $result['message'] = count($diffs) . ' line diff(s)';
        $failed++;
        $results[] = $result;
    }

    return [
        'ok' => $failed === 0 && ($passed > 0 || $skipped > 0),
        'checked' => count($jsonFiles),
        'passed' => $passed,
        'skipped' => $skipped,
        'failed' => $failed,
        'results' => $results,
    ];
}

function respondCli(array $report, bool $verbose): int
{
    if ($report['checked'] === 0) {
        fwrite(STDERR, "No week JSON files found in weeks/\n");
        return 1;
    }

    foreach ($report['results'] as $result) {
        $status = !empty($result['skipped']) ? 'SKIP' : ($result['ok'] ? 'PASS' : 'FAIL');
        $week = $result['week'];
        $message = $result['message'];
        $links = $result['eventLinks'];

        echo "{$status} {$week}: {$message}";
        if (empty($result['skipped'])) {
            echo " (event links {$links['rendered']}/{$links['expected']}";
            echo $links['match'] ? ", match)\n" : ", mismatch)\n";
        } else {
            echo "\n";
        }

        if (!$result['ok'] && $result['diffs'] !== []) {
            foreach ($result['diffs'] as $diff) {
                echo "  line {$diff['line']}\n";
                echo "    rendered: {$diff['rendered']}\n";
                echo "    expected: {$diff['expected']}\n";
            }

            if (!$verbose && $result['lineDiffs'] > count($result['diffs'])) {
                $remaining = $result['lineDiffs'] - count($result['diffs']);
                echo "  ... and {$remaining} more line diff(s); use --verbose\n";
            }
        }
    }

    echo PHP_EOL;
    $skipped = (int) ($report['skipped'] ?? 0);
    if ($skipped > 0) {
        echo "Skipped {$skipped} week file(s) without golden partials.\n";
    }
    echo $report['ok']
        ? "All {$report['passed']} golden week file(s) match.\n"
        : "{$report['passed']}/{$report['checked']} week file(s) passed.\n";

    return $report['ok'] ? 0 : 1;
}

function respondBrowser(array $report, bool $verbose): void
{
    header('Content-Type: text/html; charset=utf-8');
    $title = $report['ok'] ? 'Calendar verify passed' : 'Calendar verify failed';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . $title . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:52rem;margin:2rem auto;line-height:1.5}';
    echo 'code,pre{background:#f4f4f4;padding:.2rem .35rem;border-radius:.25rem}';
    echo '.pass{color:#0a7a2f}.fail{color:#b00020}pre{overflow:auto;padding:1rem}</style></head><body>';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>Checked ' . (int) $report['checked'] . ' week file(s); ';
    echo (int) $report['passed'] . ' passed.</p><ul>';

    foreach ($report['results'] as $result) {
        $class = $result['ok'] ? 'pass' : 'fail';
        echo '<li class="' . $class . '"><code>' . htmlspecialchars((string) $result['week'], ENT_QUOTES, 'UTF-8') . '</code>: ';
        echo htmlspecialchars((string) $result['message'], ENT_QUOTES, 'UTF-8') . '</li>';

        if ($verbose && !$result['ok'] && $result['diffs'] !== []) {
            echo '<li><pre>';
            foreach ($result['diffs'] as $diff) {
                echo 'line ' . $diff['line'] . "\n";
                echo 'rendered: ' . $diff['rendered'] . "\n";
                echo 'expected: ' . $diff['expected'] . "\n\n";
            }
            echo '</pre></li>';
        }
    }

    echo '</ul>';
    echo '<p>Run from CLI: <code>php verify.php</code> or <code>php generate.php &amp;&amp; php verify.php</code></p>';
    echo '</body></html>';
}

function main(array $argv): int
{
    $verbose = isVerbose($argv);

    try {
        $jsonFiles = resolveWeekFiles($argv);
        $report = verifyWeeks($jsonFiles, $verbose);
    } catch (Throwable $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            return 1;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<p class="fail">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        return 1;
    }

    if (PHP_SAPI === 'cli') {
        return respondCli($report, $verbose);
    }

    respondBrowser($report, $verbose);

    return $report['ok'] ? 0 : 1;
}

exit(main($argv));
