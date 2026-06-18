# cursor-cringe

Proof of concept: parse cringe.com-style calendar source text into per-week JSON, render it to HTML, and regression-test against golden output from the live calendar pipeline.

Live calendar scripts live in the sibling [`calendar`](../calendar/) directory (not in this repo).

## Requirements

- PHP 8.1+ (CLI and a web server for browser use)

## Quick start

```bash
# Generate JSON from the latest data/*-noTag.txt (or pass a file path)
php generate.php
php generate.php data/20260615-noTag.txt

# Regression-test rendered JSON against golden HTML
php verify.php

# Typical check after parser or renderer changes
php generate.php && php verify.php
```

Browser:

- [`generate.php`](generate.php) — generate week JSON
- [`verify.php`](verify.php) — run regression checks
- [`index.php`](index.php) — week viewer (`?date=YYYYMMDD`, optional `&tags=karaoke,comedy`)
- [`venue.php`](venue.php) — all events at one venue for the week (`?venue=ace-of-cups&date=YYYYMMDD`)

**Calendar day boundary:** “Today” rolls at **4:00am America/New_York** (not midnight). Before 4am counts as the previous calendar day so late-night shows stay on the right listing. The live site uses a `-3 hours` offset (~3am rollover); this POC intentionally uses 4am for longer rave/nightlife schedules.

## Workflow

### Day-to-day development

1. Edit or replace source text in `data/YYYYMMDD-noTag.txt`.
2. Run `php generate.php` to write `weeks/YYYYMMDD.json` (one file per week; filename is that week's Monday).
3. Run `php verify.php` to confirm rendered output still matches golden HTML.

`verify.php` renders each `weeks/*.json` through `WeekRenderer` and diffs byte-for-byte against the matching `weeks/*.html`. It also reports event-level link counts as a quick sanity check.

### Refreshing golden HTML

When the live calendar pipeline changes (or you fix a bug in `calendar/streamParseAll4Weeks.php`), update the reference files:

1. Run the live parser on the same source period:
   ```bash
   # from the live calendar directory
   php streamParseAll4Weeks.php
   ```
2. Copy the generated week partials into this repo as golden files:
   ```bash
   cp ../calendar/weeks/20260615.php weeks/20260615.html
   cp ../calendar/weeks/20260622.php weeks/20260622.html
   # ... one per week in the 4-week period
   ```
3. Regenerate JSON and verify:
   ```bash
   php generate.php data/20260615-noTag.txt
   php verify.php
   ```
4. Commit the updated `weeks/*.html` (and `weeks/*.json` if they changed) together so the golden files and JSON stay in sync.

**Convention:** source filename date is always a **Monday**. Each output week file is named for that week's Monday (`20260615.json` = week of Mon Jun 15, 2026).

## Project layout

| Path | Role |
|------|------|
| `data/` | Source `-noTag.txt` files (hand-edited calendar text) |
| `weeks/*.json` | Generated week data |
| `weeks/*.html` | Golden HTML from live `streamParseAll4Weeks.php` (regression baseline) |
| `lib/Calendar.php` | Parser (adapted from live `streamParseAll4Weeks.php`) |
| `lib/WeekRenderer.php` | JSON → HTML renderer (matches live `byDate()` output) |
| `generate.php` | CLI/browser JSON generator |
| `verify.php` | Regression harness |
| `index.php` | Week viewer with custom swipeable day carousel |
| `assets/week-carousel.js` | Vanilla JS day carousel (swipe/drag, keyboard) |
| `assets/event-filter.js` | Client-side event type filter |
| `assets/week-view.css` | Week viewer styles |
| `lib/EventClassifier.php` | Keyword-based event tags (from JSON-LD classify rules) |
| `lib/VenueUtils.php` | Venue slugs and week schedule lookup |
| `lib/VenueWeekRenderer.php` | Venue week page HTML |

## Live pipeline (reference)

Upstream of parsing on the live site:

1. `renumberdays.php` — roll archive forward
2. Hand-edit bare dates in the `-noTag` file
3. `streamAddDOW.php` — add missing day-of-week labels
4. `streamParseAll4Weeks.php` — parse → `calendar/weeks/YYYYMMDD.php` HTML partials

This POC currently ports step 4 (parse + render). Steps 1–3 remain in the live workflow.

## verify.php options

```bash
php verify.php                  # all weeks/*.json
php verify.php --verbose        # show every differing line on failure
php verify.php weeks/20260615.json   # single week
```

Exit code `0` = all weeks byte-identical to golden HTML; `1` = failure (suitable for CI or a pre-commit hook).
