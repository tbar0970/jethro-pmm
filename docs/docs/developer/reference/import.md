---
title: CSV person importer
---

# CSV person importer

`View_Admin__Import` (`views/view_10_admin__7_import.class.php`, `?view=admin__import`, sysadmin
only) imports persons, families, groups and notes. User-facing documentation:
[Importing Persons](../../administration/import.mdx).

The parse and apply logic lives in `Person_Importer` (`include/person_importer.class.php`); the
view is a thin UI wrapper around it, and `scripts/import_persons.php` drives the same class
from cron.

## Stages

1. **begin**: the upload form. Upload is CSV (`,`); pasted text is TSV (`\t`); a Google Sheets
   link is fetched as CSV (the named tab, the tab named by a `#gid=`, or the last tab). The Google
   fields mirror the script's options: URL, optional tab, and an optional service-account key
   upload that is used for that request only.
2. **preview**: `Person_Importer::parse()` builds the plan into `$_SESSION['import']`
   (`new_families`, `new_persons`, `person_updates`, `family_updates`, `new_groups`,
   `matched_groups`, ...) and the view renders `_printConfirmView()`. Row problems are
   `E_USER_NOTICE`/`E_USER_WARNING`s captured by `_captureErrors()`/`_handleError()`. Any captured
   error sends the user back to **begin** with a per-row error list.
3. **import**: `Person_Importer::apply()` replays the plan inside one transaction. Any error rolls
   everything back. The view then redirects by `<script>` to the import's group.

## Sources

The begin view renders one radio per `Import_Source` (`include/import_source.class.php`).
`Import_Source::printChooser()` prints the radios and a show/hide container per source; each
source prints its own fields (`printFields()`) and turns the submitted request into a temporary
CSV plus separator (`fetch()`), which `Person_Importer::parse()` consumes.

### Adding a source

Subclass `Import_Source`, return an instance from `getSources()`, and implement
`printFields()`/`fetch()`. The view and the preview/confirm stages stay unchanged.

### Layering: UI adapter vs transport

`Import_Source` is a web adapter, not a general data-source layer. It exists so the begin view
can render fields and read them from `$_REQUEST`/`$_FILES`; `printFields()` has no meaning in a
CLI context.

The reusable transport belongs in a client class: `Google_Sheets_Client` resolves links, fetches
CSV and handles service-account auth. `Person_Importer` is shared by both front ends.
`scripts/import_persons.php` calls those directly and keeps its own flag parsing; it does not use
`Import_Source`.

Routing the CLI through `Import_Source` would mean translating flags into a `$_REQUEST`-shaped
array and fabricating a `$_FILES` entry — an adapter for an adapter — while the only shared step
would be the thin `fetch()` wrapper around the client. Keep shared behaviour in the transport
client and a thin adapter at each front end.

For a Wufoo source, that means a `Wufoo_Client` (API calls, pagination, entry-to-CSV, field
mapping) shared by `Import_Source_Wufoo` and a `--wufoo` script option, rather than compelling
the CLI to use the source class.

## Columns

`Person_Importer::getSampleHeader()` is the single list of recognised columns.
`getSampleHeader(TRUE)` returns the required subset (`last_name`, `first_name`, `age_bracket`).
`call_sample_import` builds the downloadable sample file from the full list. Headers are
normalised by `_stringToKey()` (lower case, spaces → `_`). The header `id` is renamed to
`personid`, because Jethro's own CSV exports (`call_csv`, report CSV) head the person ID column
`ID`. The aliases `mobile` and `notes` are accepted for `mobile_tel` and `note`, so form exports
need no renaming.

Optional columns fall back as follows:

- `family_name`: when the file has no `family_name` column at all, the row's `last_name` is used,
  so form exports that collect only a surname still group people into families. Files that do
  have the column keep the usual continuation semantics: a blank cell stays in the family above.
- `status`: blank status is only defaulted for new persons (to the status marked `is_default`);
  a blank value on a matched row leaves the existing status unchanged. A non-blank value must be
  an existing status label (case-insensitive); an unknown label is a row error that lists the
  available labels.
- `congregation`: may be blank. It is still validated against the person's status, so a status
  that requires a congregation raises a row error when the cell is empty.

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

## Headless use (cron)

`scripts/import_persons.php` fetches a CSV from a Google Sheet or local file, validates it with
`Person_Importer`, and applies it in one transaction. Typical target: a Google Sheet tab exported
as CSV.

```bash
php scripts/import_persons.php \
  --google-spreadsheet='https://docs.google.com/spreadsheets/d/<id>/edit?usp=sharing' \
  --create-group='Signup sheet import' \
  --match-existing
```

- `--google-spreadsheet=<url>` accepts the share/edit link shown in the browser, an existing
  export/gviz/pub link, or a published (`/d/e/<id>/pubhtml`) link. Share/edit links are rewritten
  to `.../export?format=csv`, preserving a `gid` tab selector if present.
  `--google-spreadsheet-tab=<name>` imports a named tab (added as `sheet=`); without it, a
  `#gid=` in the link wins, otherwise the **last tab** is used. Google's plain CSV export would
  return the first tab. Hidden tabs are not included in that lookup, so name one explicitly.
  Published links need a `gid`, because they have no tab-name lookup.
  `--file=<path>` reads a local CSV instead; other source-specific options can be added later.
  The script never echoes the URL in its output. A response that starts with HTML (for example a
  link that is not publicly readable) is rejected before parsing.
- The CSV must contain the required columns (`last_name`, `first_name`, `age_bracket`); optional
  columns fall back as described under [Columns](#columns). Extra columns are ignored with a
  warning, so a source-specific key column (for example a form response ID) can be carried
  through harmlessly.
- `--group=<id>` or `--create-group=<name>` is required. Every imported person is added to that
  group, which gives an audit trail and a practical undo (remove the group membership).
  `--create-group` creates a new group on every run; for a recurring import that keeps a
  per-run audit trail, or pass `--group=<id>` to accumulate one stable group.
- `--match-existing` updates persons matched by name; `--match-email` and `--match-mobile`
  narrow the match. Without matching, rerunning the same file creates duplicates.
- `--no-overwrite` keeps existing non-blank values when updating; by default the file overwrites
  them.
- `--creator=<staff-id>` sets the staff account credited for the import. Default: the lowest
  staff id. A staff account is required because `family`, `person` and note rows have a non-null
  `creator`, and adding group members checks `PERM_EDITGROUP`.
- `--config=<path>` uses an alternate Jethro `conf.php` (default: the checkout's own). The
  functional test (`tests/functional/import/import-cli.spec.ts`) points it at
  `devbox.d/functest_jethro_server/conf.php` to import into `jethro_functest`.
- `--dry-run` validates and reports without writing.
- `--verbose` prints the same detail as the Admin import preview: the audit group, groups to be
  created/matched, and every new or updated family and person. Parse messages and per-row errors
  are printed regardless; progress goes to stderr. On success the final `Imported:` line reports
  the committed counts.
- The whole file is one transaction. Any row error rolls everything back and the script exits
  non-zero; there is no partial import.
- Without `--google-service-account`, the URL must be readable without OAuth (Share → Anyone
  with the link → Viewer); the script uses the anonymous CSV export endpoint. With a
  service-account key the sheet can stay private; see
  [Private sheets (service account)](#private-sheets-service-account).

### Private sheets (service account)

Pass `--google-service-account=<path>` to read the sheet through the Sheets API instead of the
public CSV export. The spreadsheet stays private: share it with the service account as **Viewer**
and enable the Sheets API in that project. Setup, key handling and troubleshooting:
[Google Sheets service account](../../administration/google-sheets-service-account.mdx).

The client signs a JWT with the key, exchanges it for a short-lived access token, and reads the
tab with `spreadsheets.values.get`. Only
`https://www.googleapis.com/auth/spreadsheets.readonly` is requested. The script warns if the key
path is inside the checkout. Without `--google-spreadsheet-tab`, the last visible tab is used (as
with the CSV export path); a `#gid=` in the URL selects that sheet. Published (`/d/e/...`) links
are rejected in this mode.

## History

- #1577: `personid` was briefly a required column, and the matching code read `id`/`person_id`
  instead of `personid`, so documented ID matching never happened. ID matching also ignored
  `match_existing`.

## Tests

Playwright project `import` has two specs:

- `tests/functional/import/import.spec.ts` covers the Admin → Import upload → preview → import
  flow, including `personid`/`ID` matching and a preview with no `status` column.
- `tests/functional/import/import-cli.spec.ts` runs `scripts/import_persons.php --file` against
  `jethro_functest` (via `--config`), asserting the created person/family/group and that an
  invalid row writes nothing.

The walkthrough spec checks that `testdata/import-persons.csv` reaches the preview stage.
