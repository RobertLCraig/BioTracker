# Verify the lab import against a full PKB capture

## What I need from you

**Capture your full test history from PKB and save it to `storage/app/private/pkb-tests.json`.**
Four steps in your own browser session, below. Nothing else on this card can start until that file
is on disk.

1. Log in at <https://my.patientsknowbest.com>, open **Health → Tests**, click the **Trend** tab
   and set the date range to **All**.
   Pass: every analyte is listed on the page. Fail: a short or empty list means the date range did
   not apply, and the capture would miss older tests.
2. Press **F12** → **Console**, type `allow pasting` and Enter if it asks, then paste the Method A
   snippet from [../../spec/lab-results-design.md](../../spec/lab-results-design.md) §11.
   Pass: the console reports a test count and the browser downloads `pkb-tests.json`. Fail: a 403
   or an empty `tests[]` means the session expired, so log in again and re-run.
3. Open the downloaded file and check it holds more than the six lipids (search for
   `testHistoryMetadata` and count the hits).
   Pass: many analytes. Fail: six only means step 1's Trend/All did not take.
4. Save it as `storage/app/private/pkb-tests.json` in this repo and say so in the session. That
   path is gitignored (verified), so the results never reach git. Do not paste the contents into
   chat.

**Pass** is that file on disk carrying more than the six lipids, with the analyte count said out
loud so the import can be checked against it.

**Fail** is named per step above. The one to watch is step 3: six results only means step 1's
Trend/All did not apply, and importing that file would re-verify the field map against the same
single panel it was written from, which is the thing this card exists to stop.

**Why it needs you** It needs your logged-in PKB session. There is no export button and no API to
authenticate against, so this is the one step of Phase 6 that no agent and no test can reach.

## Why
Phase 6 shipped verified against a single captured lipid panel (1 panel, 6 results). The field map
in §2.5 was written from that one sample, so every other shape it claims to handle (text-only
results, comparators like `>60`, non-numeric ranges, embargoed `delayedDisplayDate` results,
withdrawn and corrected status) is mapped but has never met real data.

## Not this card
Not curating the analyte catalog, not adding alias matching (0005), not building any UI (0003),
and not merging the branch (0002). This card ends when one full capture has been imported and the
mismatches are written down.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN the full capture is imported, THE APP SHALL create panels and results without an
      unhandled exception, and report how many of each it created.
- [ ] #2 WHEN the same file is imported a second time, THE APP SHALL create no new rows, being
      idempotent on `external_id`.
- [ ] #3 IF an analyte in the capture matches no seeded definition, THEN THE APP SHALL create an
      `is_curated = false` definition and attach the result to it, rather than dropping the row.
- [ ] #4 WHEN a result carries a comparator, a non-numeric range, or a text-only value, THE APP
      SHALL store it without error and SHALL omit it from the trend series.
- [ ] #5 WHEN out-of-range flags are compared against the PKB portal for at least five results,
      THE APP SHALL agree with the portal on every one.
<!-- AC:END -->

## Tasks
- [ ] Rob captures `pkb-tests.json` per the steps above.
- [ ] Import it, either `POST /api/v1/lab-results/import` with a Sanctum token or
      `PkbTestImportService` under `php artisan tinker`, whichever is quicker.
- [ ] Record the counts: panels, results, definitions matched by `pkb_type_id`, matched by slug,
      auto-created uncurated.
- [ ] List every uncurated definition created, and say whether a seeded row already meant the same
      analyte. That list is the input to 0005.
- [ ] Spot-check five flags against the portal.
- [ ] Write the findings into this card before moving it on.
