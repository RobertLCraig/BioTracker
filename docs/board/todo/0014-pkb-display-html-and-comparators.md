# PKB results show their HTML and lose their > and <

## Why
The full PKB import (card 0001, 2026-10-17) showed that PKB's `value.display` is HTML, not text.
A result above the lab's limit arrives as `&gt;N unit` and a comment as two lines joined by `<br/>`. The
importer stores that string as `value_text` unchanged, so the Labs view prints `&gt;N` and `<br/>`
literally. PKB also sends `value.comparator` as null on every data point, so `value_comparator` is
never set and `>N` cannot be told from `N` anywhere but in that string.

The WBC differential (Neutrophils, Lymphocytes, Monocytes, Eosinophils, Basophils) arrives as text
only, count and percent in one string (`<count>  <percent>% 10*9/L`), so it never trends and never gets a flag
although each carries a numeric range.

## Links

**Relates to**
- `0001` - the import that found this. Which results are affected is user data, kept out of git in `storage/app/private/pkb-import-2026-10-17-findings.md`.

## Not this card
Curating the 14 uncurated definitions. Rewriting rows already stored: a re-import updates them on
`external_id`.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN a PKB display string holds HTML entities or `<br/>`, THE IMPORTER SHALL store
      `value_text` as plain text (entities decoded, line breaks as newlines).
- [ ] #2 WHEN a display string starts with `>` or `<` and a number, THE IMPORTER SHALL set
      `value_comparator` to that sign and SHALL leave `value_numeric` null, so the result stays out
      of the trend series.
- [ ] #3 WHEN a text-only result is a count followed by a percent (the WBC differential), THE
      IMPORTER SHALL store the count as `value_numeric`, so it trends and is flagged against its
      range. Rob's call whether this one is wanted.
<!-- AC:END -->

## Comments
