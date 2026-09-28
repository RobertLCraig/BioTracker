# Lab Results view in the Vue SPA

## Why
Phase 6 built the whole API surface (§6 of the design doc) and no frontend at all. Every other
domain has a view under `resources/js/views/` and a route and nav entry to reach it, so lab
results are the one feature a user cannot see in the app. The trends endpoint already returns a
series plus a range band shaped for a chart client, and `AnalyticsView.vue` already draws
Chart.js series, so the seam exists.

## Not this card
No new or changed API endpoints. No paste-parse preview UI (the 3c fallback path): open a
separate card if that is wanted. No edit/delete UI for individual results, and no change to the
report exporter, which already carries a lab section.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a signed-in user opens `/labs`, THE APP SHALL list their lab results newest first,
      grouped by panel, each row showing test name, value with unit, reference range and the
      out-of-range flag.
- [x] #2 WHEN a result is flagged high or low, THE APP SHALL mark it visibly in the list rather
      than relying on the reader comparing the value to the range.
- [x] #3 WHEN a user picks an analyte, THE APP SHALL draw its numeric series over time with the
      reference range shown as a band, using `GET /api/v1/lab-results/trends`.
- [x] #4 WHEN a user uploads a PKB JSON file, THE APP SHALL post it to
      `POST /api/v1/lab-results/import` and report how many panels and results were created.
- [x] #5 IF a result is text-only or has no numeric value, THEN THE APP SHALL still show it in the
      list and SHALL omit it from the chart.
- [x] #6 WHEN a user has no lab results, THE APP SHALL show an empty state pointing at the import
      upload rather than an empty chart.
<!-- AC:END -->

## Tasks
- [x] `resources/js/views/LabsView.vue`, following the structure of `VitalsView.vue` for the list
      and `AnalyticsView.vue` for the chart.
- [x] Route `{ path: 'labs', name: 'labs', ... }` in `resources/js/router/index.js` and a nav
      entry in `components/AppLayout.vue`.
- [x] Call the API through `composables/useApi.js`, as the other views do.
- [x] Analyte picker fed by `GET /lab-results/trends` with no `test_key`, which returns the
      available series.
- [x] File upload posting to `/lab-results/import`.
- [ ] `npm run build` clean, and check the view against the seeded demo account.
      Build is clean; the demo-account check is not done and cannot be — see the comment below.

## Comments

**2026-08-29** Built `resources/js/views/LabsView.vue`, added the `labs` route and a "Lab Results"
nav entry above Analytics. The view makes three calls on mount through `useApi`: `/lab-results`
for the rows, `/lab-panels` for the group headings, and `/lab-results/trends` with no `test_key`
for the analyte picker, then `/lab-results/trends?test_key=` when the picker changes. Upload posts
multipart `file` to `/lab-results/import` via `postForm` and reports the `panels`,
`results_created` and `results_updated` the controller returns, or its queued message for a file
over 2 MB.

Decisions I made because the repository did not settle them:

- **Grouping comes from the flat result list, not from `/lab-panels`.** `LabResultImporter`
  returns `null` for a panel when a row carries no `lab_order_id`, so manual and paste-entered
  results have `lab_panel_id = null` and would be invisible if the view iterated panels. It
  buckets `/lab-results` (already newest-first) by `lab_panel_id`, keeping first-seen order so the
  groups stay newest-first, and panel-less rows fall into a "Not part of a panel" group.
- **Decimals are trimmed in the view.** `LabResult` casts `value_numeric` and `range_*` as
  `decimal:4`, so the index resource sends `"0.8000"`. `/lab-results/trends` already casts to
  float for exactly this reason, but the index does not, and rendering "0.8000 mmol/L" is wrong
  for a lab report. The view runs them through `Number()`. Changing the resource would have been
  an API change, which this card excludes.
- **`value_text` already carries the unit** (`PkbTestImportService` builds it as `"$val $unit"`),
  so the unit is appended only on the numeric branch, never to the text fallback.
- **The reference band is two extra Chart.js datasets**, the high line filling down to the low
  line that follows it, with the legend hidden as in `AnalyticsView.vue` and a caption saying
  what the shading is.

What I could not settle from the repository:

- **No browser check.** This ran in a worktree; Herd serves the SPA from `C:\Dev\BioTracker`, so
  nothing here is reachable in a browser. `npm run build` is clean and the suite is green, but no
  one has seen this view render. It still needs one pass with `/run`.
- **The seeded demo account has no lab data.** `DemoDataSeeder` writes nothing to `lab_panels` or
  `lab_results`, so opening `/labs` on it exercises the empty state (#6) and nothing else. The
  card's "check against the seeded demo account" task therefore cannot verify #1, #2, #3 or #5 as
  written. Either import a PKB capture first (card 0001) or extend the demo seeder — that is a
  separate card, not this one, so the task is left unticked.
- **The list stops at the API's 50-row page.** `LabResultController::index` paginates at 50 and
  `/lab-panels` at 25, and this card excludes API changes, so the view does not page. Rather than
  truncate silently it prints "Showing the newest N of M results" whenever the server total
  exceeds what was loaded. Once card 0001 lands a full PKB history that will fire immediately, and
  paging (or a raised page size) is worth its own card.

Verification: `.\vendor\bin\phpunit.bat` → 10 passed, 60 assertions. There is no Pest binary in
`vendor/bin` — this project runs PHPUnit, and `docs/HANDOVER.md` says `artisan test`. I added one
test, `test_index_carries_the_fields_the_labs_view_groups_and_renders_on`, which pins the exact
index and panel fields the view groups and renders on: `lab_panel_id` present and null for a
standalone result, `value.numeric`, `range.low/high`, `abnormal_flag`, and the panel's
`lab_order_id` and `collected_at`. Without it a resource change could break the view with the
suite still green.

`.\vendor\bin\pint.bat` rewrote 66 PHP files across the whole repository, none of them touched by
this card — Pint has evidently never been run here and the house style (aligned `=>`, spaced
concatenation) differs from the Laravel preset throughout. I reverted all of it rather than bury a
one-file card in a repo-wide reformat. `pint --test` on the one PHP file I did touch reports only
`concat_space`, on a pre-existing line I did not write, so the code I added is Pint-clean. Running
Pint across the repository is a decision for Rob and belongs in its own commit.

### 2026-08-29 review (v20260829140236-162c)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 24s, run by this job rather than reported by the card.

**acceptance: defect**

**Traced, all six.** File: `resources/js/views/LabsView.vue` unless said.

- **#1** `groups` (computed) buckets rows from `/lab-results`; `LabResultController::index` sorts `orderByDesc('sampled_at')`. Columns come from `valueOf`, `rangeOf`, `flagOf`.
- **#2** `flagOf` + the `FLAGS` map. Its keys match every `AbnormalFlag` case value. Badge plus coloured row border.
- **#3** `loadTrend` calls `/lab-results/trends?test_key=`; `chartData` draws the series plus two range datasets, the high one `fill: '+1'` onto the low one. `Filler` is registered.
- **#4** `upload` posts `file` through `postForm` to `LabImportController::pkb`, and prints `panels` / `results_created` from `LabResultImporter::import`.
- **#5** `valueOf` falls back to `value.text`; `chartPoints` drops null values.
- **#6** the `v-else-if="!results.length"` block, with an Import button.

**One break.** `groups` looks panel names up in `panels`, filled from **one page** of `/lab-panels`, and `LabPanelController::index` paginates at 25. A row whose `lab_panel_id` is not on that page gets `p === undefined`, so the group is headed **"Not part of a panel"** ÔÇö the view tells the user a real panel is not a panel. Fires when the newest 50 results span over 25 panels. Fixable in the view alone.

VERDICT: defect

**scope: defect**

**Fence: held.** No API endpoint, resource, exporter, edit/delete or paste-preview code was touched. Only `LabsView.vue`, the route, the nav entry, one test and `HANDOVER.md`.

**Left half done, and worse than the card says.**

The card discloses one cap: 50 results. It does not disclose the second. `/lab-panels` pages at 25 (`LabPanelController::index`), but the view loads results 50 at a time. In `LabsView.vue`, the `groups` computed looks each result's panel up in the loaded map. A result whose panel is number 26 or later gets `undefined`, so its heading falls to the panel-less branch and reads **"Not part of a panel"**. That is AC #1's grouping printing a false heading, not just truncating.

The reason given for not paging is also wrong. `LabResultController::index` uses `paginate(50)`, which already answers `?page=2`. Looping pages in `load()` is client-only work and needs no API change, so the fence the card cites does not apply.

Task 6 is unticked and the agent says #1, #2, #3 and #5 cannot be checked ÔÇö yet all six boxes are ticked.

VERDICT: defect

**breakage: defect**

I read `LabsView.vue`, `useApi.js`, the router/nav diff, `LabResultController`, `LabPanelController`, `LabResultResource`, `LabPanelResource`, `AbnormalFlag`, `UserOwnedScope` and the added test. Flag keys, resource field names, import response keys, `postForm`, `Filler` and the `fill: '+1'` band all check out. Two things do not.

**1. The panel map is truncated, and a miss is silently relabelled.** `load()` in `LabsView.vue` fills `panels` from one page of `/lab-panels`, which `LabPanelController::index` caps at 25, while `/lab-results` returns 50 rows. The `groups` computed does `panels.value[r.lab_panel_id]`, and a lookup miss is indistinguishable from `lab_panel_id === null`, so it prints "Not part of a panel". Failure: 50 single-test orders (INR or HbA1c monitoring is exactly one result per order) means 25 groups falsely titled as having no panel, no warning. The view surfaces the 50-result cap but not this one, and `pan.data.meta.total` is available and unused. It also makes the docblock on `groups` false ÔÇö that bucket is claimed to hold only results with no `lab_order_id`.

**2. The chart goes stale after a repeat import.** `upload()` calls `load()`, and `load()` only calls `loadTrend()` when `testKey` is empty, which it is not after the first render. New rows appear in the list; the selected analyte's trend still shows pre-import points.

VERDICT: defect


**2026-08-29** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 6 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer never unticks a criterion - it is forbidden from editing acceptance at all - so the card came back with 6 of 6 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Untick what the reviewer disproved and move it back to todo/, or say here why the finding is wrong.

**2026-09-28** Manager pass: reopened #1 because the review's finding still holds on `master`:
`load()` in `LabsView.vue` fills the panel map from one page of `/lab-panels` (25 per page, from
`LabPanelController::index`) while `/lab-results` returns 50, so a result whose panel is past the
first page is grouped under a false "Not part of a panel" heading. Also still open from the same
review: after an upload, `load()` skips `loadTrend()` when `testKey` is already set, so the chart
shows pre-import points.

**2026-09-28** RESULT: done
TESTS: +4 new, all green
TOUCHED: resources/js/labs.js
resources/js/views/LabsView.vue
tests/js/labs.test.mjs
docs/HANDOVER.md
docs/board/in-progress/0003-lab-results-vue-view.md
OUT-OF-SCOPE: none

Fixed both review findings. `load()` now fetches `/lab-panels` page by page (`?page=`, which
`paginate(25)` already answers, so no API change) until every `lab_panel_id` in the loaded results
is found or `meta.last_page` is reached. It stops early, so a long history costs one request in the
usual case. The grouping now tells a missing panel apart from no panel: a non-null
`lab_panel_id` whose panel did not load is headed "Lab panel", never "Not part of a panel".

The page walk and the grouping moved out of the SFC into `resources/js/labs.js`, so they can be
tested. The repo had no JS test harness. I used Node's built-in runner, not a new dependency:
`node --test tests/js/labs.test.mjs` (the directory form does not resolve on Windows). It is not
part of `phpunit`, so a PHP-only run will not see it. Watched red first: the page-26 test got
`undefined` and the missing-panel test got "Not part of a panel". The early-stop test was green
before the fix too; it guards the new loop against walking every page.

Stale trend after upload: `load()` now calls `loadTrend()` every time, not only when no analyte was
picked. This harness cannot express that case: it lives in the SFC's reactive state, and there is
no Vue test utils here to mount it. It is fixed but not tested.

Still open: the demo-account task. The demo seeder has no lab data, and a worktree cannot be
browsed, so this view still needs one `/run` pass against a PKB import. `.\vendor\bin\pest.bat`
does not exist; `.\vendor\bin\phpunit.bat` → 15 passed, 75 assertions. No PHP file touched, so
Pint had nothing to check. `npm run build` clean.

### 2026-09-28 review (v20260928190453-5b3b)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: sound**

I checked each criterion against the code. I could not break any of them.

- **#1:** `groupResults` in `resources/js/labs.js` groups the rows. The rows come from `/lab-results`, which is sorted newest first. `valueOf`, `rangeOf` and `flagOf` in `LabsView.vue` fill the columns. The old "Not part of a panel" bug is fixed. `loadPanels` now fetches `/lab-panels` page by page until it finds every panel. A panel that still does not load is headed "Lab panel". It is never called "Not part of a panel".
- **#2:** The `FLAGS` map and `flagOf` give each high or low row a badge and a coloured left border.
- **#3:** `loadTrend` calls `/lab-results/trends` with `test_key`. `chartData` draws the band as two range datasets, with `fill: '+1'`. The `Filler` plugin (it shades the area between two lines) is registered.
- **#4:** `upload` posts `file` through `postForm`. It reports `panels`, `results_created` and `results_updated`.
- **#5:** `valueOf` falls back to `value.text`. `chartPoints` drops points that have a null value.
- **#6:** The `v-else-if="!results.length"` block shows the empty state with an Import button.

The old stale-chart bug is also fixed. `load()` now always calls `loadTrend()`, and `upload` calls `load()` after an import.

No criterion is disproved. The demo-account check is still not done, but that is an unticked task, not a criterion.

VERDICT: sound

**scope: defect**

Both findings from the last review are now fixed in the code. `loadPanels` in `resources/js/labs.js` pages through `/lab-panels` until it finds every panel. `groupResults` now heads a panel it could not load "Lab panel", not "Not part of a panel". `load()` in `LabsView.vue` now always calls `loadTrend()`.

**The fence held.** The diff also shows `LabTestDefinition::matchAlias`, the seeder aliases, `PRD.md`, `DATA-MODEL.md` and `DECISIONS.md`. Those come from cards 0004 and 0005, which were merged into the same range. This card's TOUCHED list does not include them. I do not count them against this card.

**One item is still half done.** `load()` in `LabsView.vue` still fetches one page of `/lab-results`, which is 50 rows. The view then prints "Showing the newest N of M". The card says the view does not page because paging "would be an API change". That reason is wrong. `LabResultController::index` uses `paginate(50)`, so it already accepts `?page=`. The builder proved this when it wrote `loadPanels`, which walks pages on `/lab-panels` with no API change. The same loop on `/lab-results` needs no API change either. After a full PKB import (card 0001), AC #1 will list only the newest 50 results, not "their lab results".

The demo-account task is still unticked, and the card says so.

UNMET: #1 `load()` in `LabsView.vue` shows only the first 50-row page of `/lab-results`, and the API already pages, so the "no API change" reason for stopping there does not hold.

VERDICT: defect

**breakage: sound**

I tried to break the fix. I could not.

**What I checked:**

- **Panel page walk.** `loadPanels` in `resources/js/labs.js` asks for `/lab-panels` page by page, using `get(path, params)` in `useApi.js`. It stops when it has found every panel it needs, or when it reaches `meta.last_page`. So a panel on page 2 or later now loads. The page order does not matter.
- **Wrong heading.** `groupResults` in `labs.js` now shows "Lab panel" for a panel ID that did not load. Only a result with a null `lab_panel_id` gets "Not part of a panel".
- **Stale chart.** `load()` in `LabsView.vue` now runs `loadTrend()` every time, also when an analyte is already picked. So after an upload, the chart gets the new points.
- **No callers left behind.** `LabsView.vue` is the only user of the two helpers.

**One small note, not a defect:** the docblock on `groupResults` says results with no panel go into a "trailing" bucket. That is not true. The code keeps first-seen order, so that group can show up anywhere in the list. Only the words are wrong. Nothing on screen breaks.

This finding does not disprove any criterion.

VERDICT: sound

**acceptance**

- **#1 reopened**, by the scope lens: `load()` in `LabsView.vue` shows only the first 50-row page of `/lab-results`, and the API already pages, so the "no API change" reason for stopping there does not hold.

**2026-09-28** RESULT: done
TESTS: +2 new, all green
TOUCHED: resources/js/labs.js
resources/js/views/LabsView.vue
tests/js/labs.test.mjs
docs/HANDOVER.md
docs/board/in-progress/0003-lab-results-vue-view.md
docs/board/todo/0007-lab-results-index-stable-page-order.md
OUT-OF-SCOPE: 0007

The reviewer was right: `paginate(50)` already answers `?page=`. New `loadResults(get)` in
`labs.js` walks every page of `/lab-results` until `meta.last_page`, the same way `loadPanels`
walks `/lab-panels`. `load()` uses it. The "Showing the newest N of M" line and its `total` ref are
gone, because the list is now whole. No API change.

Test-first: I moved the old one-page fetch into `loadResults` unchanged, then wrote
`every_result_is_listed_not_only_the_first_50_row_page`. It went red with `actual: 50, expected:
120`. Then the fix made it green.

The index sorts by `sampled_at` alone, and a panel's rows share one `sampled_at`, so tied rows can
repeat or go missing across a page edge. The view drops a repeated id
(`a_row_repeated_across_a_page_edge_is_listed_once`, watched red with the guard taken out). It
cannot recover a skipped row: only a tie-break in the API fixes that, which this card excludes. That
is card 0007.

Also corrected the `groupResults` docblock the last review flagged ("trailing bucket" was wrong).

Suite: `.\vendor\bin\pest.bat` does not exist; `.\vendor\bin\phpunit.bat` → 15 tests, 75
assertions, OK. `node --test tests/js/labs.test.mjs` → 6 pass. `npm run build` clean. No PHP file
touched, so Pint had nothing to check. The demo-account task stays open: still no browser from a
worktree, and the demo seeder still has no lab data.

