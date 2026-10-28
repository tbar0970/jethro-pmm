---
title: CSV person importer
---

# CSV person importer

`View_Admin__Import` (`views/view_10_admin__7_import.class.php`, `?view=admin__import`, sysadmin
only) imports persons, families, groups and notes. User-facing documentation:
[Importing Persons](../../administration/import.mdx).

## Stages

1. **begin**: the upload form. Upload is CSV (`,`); pasted text is TSV (`\t`).
2. **preview**: `_preparePreview()` parses the file into `$_SESSION['import']` (`new_families`,
   `new_persons`, `person_updates`, `family_updates`, `new_groups`, `matched_groups`, ...) and
   renders `_printConfirmView()`. Row problems are `E_USER_NOTICE`/`E_USER_WARNING`s captured by
   `_captureErrors()`/`_handleError()`. Any captured error sends the user back to **begin** with a
   per-row error list.
3. **import**: `_processImport()` replays the session data inside one transaction. Any error rolls
   everything back. It then redirects by `<script>` to the import's group.

## Columns

`getSampleHeader()` is the single list of recognised columns. `getSampleHeader(TRUE)` returns the
required subset (`family_name`, `last_name`, `first_name`, `congregation`, `status`,
`age_bracket`). `call_sample_import` builds the downloadable sample file from the full list.
Headers are normalised by `_stringToKey()` (lower case, spaces → `_`). The header `id` is renamed
to `personid`, because Jethro's own CSV exports (`call_csv`, report CSV) head the person ID column
`ID`.

## Matching existing persons — `_findExistingPerson()`

- **Non-blank `personid`**: authoritative. It returns that person, archived or not, ignoring names.
  It raises a row error, and never falls back to name matching, when the value is not a positive
  integer, when no such person exists, or when `match_existing` is off. A wrong explicit ID would
  otherwise silently create a duplicate of the person the file named.
- **Blank or absent `personid`**: when `match_existing` is on, it matches by exact first + last
  name. `match_email`/`match_mobile_tel` discard candidates whose non-blank value differs. Only a
  unique match counts.

A matched row updates the person and their family (`person_updates`/`family_updates`). Following
unmatched rows of the same family become `new_persons` in that existing family. The
`overwrite_existing` flag is passed to `DB_Object::fromCsvRow()`, which never overwrites with a
blank value.

## History

- #1577: `personid` was briefly a required column, and the matching code read `id`/`person_id`
  instead of `personid`, so documented ID matching never happened. ID matching also ignored
  `match_existing`.

## Tests

Playwright project `import` (`tests/functional/import/import.spec.ts`) covers the full upload →
preview → import flow. The walkthrough spec checks that `testdata/import-persons.csv` reaches the
preview stage.
