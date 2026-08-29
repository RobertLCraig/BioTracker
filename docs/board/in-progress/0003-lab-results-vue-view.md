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
- [ ] #1 WHEN a signed-in user opens `/labs`, THE APP SHALL list their lab results newest first,
      grouped by panel, each row showing test name, value with unit, reference range and the
      out-of-range flag.
- [ ] #2 WHEN a result is flagged high or low, THE APP SHALL mark it visibly in the list rather
      than relying on the reader comparing the value to the range.
- [ ] #3 WHEN a user picks an analyte, THE APP SHALL draw its numeric series over time with the
      reference range shown as a band, using `GET /api/v1/lab-results/trends`.
- [ ] #4 WHEN a user uploads a PKB JSON file, THE APP SHALL post it to
      `POST /api/v1/lab-results/import` and report how many panels and results were created.
- [ ] #5 IF a result is text-only or has no numeric value, THEN THE APP SHALL still show it in the
      list and SHALL omit it from the chart.
- [ ] #6 WHEN a user has no lab results, THE APP SHALL show an empty state pointing at the import
      upload rather than an empty chart.
<!-- AC:END -->

## Tasks
- [ ] `resources/js/views/LabsView.vue`, following the structure of `VitalsView.vue` for the list
      and `AnalyticsView.vue` for the chart.
- [ ] Route `{ path: 'labs', name: 'labs', ... }` in `resources/js/router/index.js` and a nav
      entry in `components/AppLayout.vue`.
- [ ] Call the API through `composables/useApi.js`, as the other views do.
- [ ] Analyte picker fed by `GET /lab-results/trends` with no `test_key`, which returns the
      available series.
- [ ] File upload posting to `/lab-results/import`.
- [ ] `npm run build` clean, and check the view against the seeded demo account.
