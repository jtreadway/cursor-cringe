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

# Regression-test rendered JSON against golden PHP partials
php verify.php

# Typical check after parser or renderer changes
php generate.php && php verify.php
```

Browser:

- [`generate.php`](generate.php) — generate week JSON
- [`verify.php`](verify.php) — run regression checks
- [`index.php`](index.php) — calendar viewer (see URL parameters below)
- [`venue.php`](venue.php) — all events at one venue for 4 weeks (`?venue=ace-of-cups&date=YYYYMMDD`); venue names on the index link here

### Views

- **By day** (`view=day`, default) — swipeable carousel of day panels
- **By week** (`view=week`) — all venues grouped for the week

Top nav in the filter card:

- **Day view** — week strip (`prev week` · date range · `next week`), then Mo–Su day pills, plus day/week grid icons
- **Week view** — prev week · week range · next week, plus day/week grid icons

Filter row: **Filter:** + find text field + venue/event pills; tap pills or funnel icon to expand. Find field stays in place and widens when open.

### Filters

Collapsible filter card on index and venue pages:

- **Find** — text search (2+ characters)
- **All N / My venues N** — scope toggle (`scope=favorites` for my venues)
- **Type pills** — tag filters (`tags=karaoke,comedy`, comma-separated)
- **clear** — reset filters client-side (sets `prefs=neutral`, does not write cookies)
- **save** (index only) — commit current filters, view, scope, and favorites to cookies

### Saved preferences (cookies)

| Cookie | Purpose |
|--------|---------|
| `cringe_view` | `day` or `week` |
| `cringe_tags` | Comma-separated tag slugs |
| `cringe_find` | Find text |
| `cringe_scope` | `favorites` when my-venues scope is saved |
| `cringe_favorites` | Comma-separated favorite venue slugs |
| `cringe_favorites_engaged` | User has used favorites |
| `cringe_recent` | Recently viewed venues (auto-tracked) |

On first visit with no filter params in the URL, the server redirects to saved cookie preferences. Add `prefs=neutral` to skip that redirect and browse with no saved filters applied.

### URL parameters

| Param | Values | Notes |
|-------|--------|-------|
| `date` | `YYYYMMDD` | Selected day or week anchor |
| `view` | `day`, `week` | Layout mode |
| `tags` | comma-separated slugs | Event type filters |
| `find` | text | Search (2+ chars) |
| `scope` | `favorites` | My venues only |
| `prefs` | `neutral` | Skip saved-prefs redirect; mark intentional neutral browse |
| `venue` | slug | Venue page only |

Nav links stay in sync when filters change client-side (`calendar-nav-sync.js` rebuilds hrefs from the current URL).

**Calendar day boundary:** “Today” rolls at **4:00am America/New_York** (not midnight). Before 4am counts as the previous calendar day so late-night shows stay on the right listing. The live site uses a `-3 hours` offset (~3am rollover); this POC intentionally uses 4am for longer rave/nightlife schedules.

## Workflow

### Day-to-day development

1. Edit or replace source text in `data/YYYYMMDD-noTag.txt`.
2. Run `php generate.php` to write `weeks/YYYYMMDD.json` (one file per week; filename is that week's Monday).
3. Run `php verify.php` to confirm rendered output still matches golden PHP partials.

`verify.php` renders each `weeks/*.json` through `WeekRenderer` and diffs byte-for-byte against the matching `weeks/*.php`. It also reports event-level link counts as a quick sanity check.

### Refreshing golden PHP partials

When the live calendar pipeline changes (or you fix a bug in `calendar/streamParseAll4Weeks.php`), update the reference files:

1. Run the live parser on the same source period:
   ```bash
   # from the live calendar directory
   php streamParseAll4Weeks.php
   ```
2. Copy the generated week partials into this repo as golden files:
   ```bash
   cp ../calendar/weeks/20260615.php weeks/20260615.php
   cp ../calendar/weeks/20260622.php weeks/20260622.php
   # ... one per week in the 4-week period
   ```
3. Regenerate JSON and verify:
   ```bash
   php generate.php data/20260615-noTag.txt
   php verify.php
   ```
4. Commit the updated `weeks/*.php` (and `weeks/*.json` if they changed) together so the golden files and JSON stay in sync.

**Convention:** source filename date is always a **Monday**. Each output week file is named for that week's Monday (`20260615.json` = week of Mon Jun 15, 2026).

## Project layout

| Path | Role |
|------|------|
| `data/` | Source `-noTag.txt` files (hand-edited calendar text) |
| `weeks/*.json` | Generated week data |
| `weeks/*.php` | Golden PHP partials from live `streamParseAll4Weeks.php` (regression baseline) |
| `lib/Calendar.php` | Parser (adapted from live `streamParseAll4Weeks.php`) |
| `lib/WeekRenderer.php` | JSON → HTML renderer (matches live `byDate()` output) |
| `generate.php` | CLI/browser JSON generator |
| `verify.php` | Regression harness |
| `index.php` | Calendar viewer (day/week views, filters, saved prefs) |
| `venue.php` | Single-venue week view |
| `assets/week-carousel.js` | Vanilla JS day carousel (swipe/drag, keyboard) |
| `assets/event-filter.js` | Client-side filtering and URL sync |
| `assets/calendar-prefs.js` | Cookie load/save and URL builders |
| `assets/calendar-nav-sync.js` | Keeps nav hrefs in sync with filter URL state |
| `assets/week-view.css` | Calendar and filter styles |
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

Exit code `0` = all weeks byte-identical to golden PHP partials; `1` = failure (suitable for CI or a pre-commit hook).
