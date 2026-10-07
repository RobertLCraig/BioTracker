# Download your full test history from Patients Know Best

## What I need from you
Save your whole PKB test history as `C:\Dev\BioTracker\storage\app\private\pkb-tests.json`.
1. Log in at <https://my.patientsknowbest.com>. Open **Health → Tests**, click the **Trend** tab, set the date range to **All**. Pass: every test you have ever had is listed, not just cholesterol.
2. Press **F12**, open **Console**, type `allow pasting` and Enter if it asks. Paste the snippet under "Method A" in `C:\Dev\BioTracker\docs\spec\lab-results-design.md` (section 11) and press Enter. Pass: it prints "Done — saved N tests" and your browser downloads `pkb-tests.json`. Fail: an error or N = 0 means you were logged out; log in and run it again.
3. Move the file to `C:\Dev\BioTracker\storage\app\private\pkb-tests.json` and write N in Comments. Pass: N is well above 6. Do not paste the file's contents into a chat.

**My recommendation:** do it. It is the only way to know the lab import works on more than one blood test.
Paste to answer: `**2026-10-07** **Decided:** File saved, N = <number>.` (or `Not wanted, discard.`)

## What you need to know
- The lab import was built and checked against one cholesterol panel only: 6 results.
- Everything else it claims to handle (text-only results, values like `>60`, odd reference ranges, corrected results) has never met real data.
- PKB has no export button and no API. Only your logged-in browser can fetch it, so no agent can.
- The snippet only reads your own data over your own session. It sends nothing anywhere.
- The file stays out of git: that folder is ignored.

## See it
- PKB: <https://my.patientsknowbest.com>
- Where the results will show: <https://biotracker.test/labs>

---
## For the agent (Rob can stop reading here)
When the file is on disk: import it (`POST /api/v1/lab-results/import` with a Sanctum token, or `PkbTestImportService` in tinker). Record counts: panels, results, definitions matched by `pkb_type_id`, by slug, by alias, and auto-created uncurated. List every uncurated definition created and whether a seeded row already meant the same analyte; that list feeds a second alias pass (card 0005's aliases were guessed from UK pathology naming). Spot-check five flags against the portal. Write findings here.

Not this card: curating the catalog, UI, alias matching itself.

### Acceptance
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

**Related:** 0003 (done) is the Labs view this data appears on; the demo account has no lab rows, so it has only been seen empty. 0005 (done) is alias matching, which this capture tests on real names.

## Comments
