# Jethro Regression Tests

`jethro_regression_tests.nu` is a [Nushell](https://www.nushell.sh/) script that regression-tests a Jethro instance by fetching a set of representative URLs, saving the normalised HTML (or binary hash) to `saved/`, and later comparing fresh responses against those snapshots. If nothing has changed in the application, every test passes. If a code change accidentally alters a page's output, the relevant test fails and prints a `vimdiff` command to inspect the difference.

## Prerequisites

- [Nushell](https://www.nushell.sh/) (`nu`).
- Network access to the target Jethro instance

## Configuration

The regression tests should work out the box when run with the provided `credentials.json`, running against an online demo instance:

```json
{
    base_url: "https://easyjethro.com.au/demo/",
    username: "demo",
    password: "6MO7iS9cjcuj"
}
```

To regression-test local changes, [obtain the demo data](https://github.com/tbar0970/jethro-pmm/pull/1398) and load it into a local MySQL database. `jethro_regression_tests.nu` assumes the demo data is running, although it is not hard to modify IDs to regression-test a different data set.


## Usage

```
nu jethro_regression_tests.nu --saveall                     # fetch all pages and save snapshots
nu jethro_regression_tests.nu --save <id>                   # fetch one page by id and save its snapshot
nu jethro_regression_tests.nu --testall                     # compare all pages against saved snapshots
n:%su jethro_regression_tests.nu --test <id[,id,...]>          # compare a subset of pages
nu jethro_regression_tests.nu --show <id>                   # print a page's normalised output to stdout
```

## Example: `--saveall` output

Run against `https://easyjethro.com.au/demo/`:

```
== Public pages ==
  Saving public_home: https://easyjethro.com.au/demo//public/?view=home
  Saving login: https://easyjethro.com.au/demo//
  Saving public_rosters_view_2: https://easyjethro.com.au/demo//public/?view=rosters&roster_view=2
  Saving public_roster_role_1: https://easyjethro.com.au/demo//public/?view=_roster_role_description&role=1
  Saving public_roster_role_2: https://easyjethro.com.au/demo//public/?view=_roster_role_description&role=2
  Saving public_rosters: https://easyjethro.com.au/demo//public/?view=rosters
  Saving public_rosters_view_1: https://easyjethro.com.au/demo//public/?view=rosters&roster_view=1
  Saving public_rosters_view_3: https://easyjethro.com.au/demo//public/?view=rosters&roster_view=3
  Saving public_check_in: https://easyjethro.com.au/demo//public/?view=check_in
  Saving public_roster_roles: https://easyjethro.com.au/demo//public/?view=_roster_role_description
  Saving public_roster_role_6: https://easyjethro.com.au/demo//public/?view=_roster_role_description&role=6
    Saved -> saved/login.html
    Saved -> saved/public_home.html
    ... (output is interleaved due to parallel fetching)

== Admin pages ==
  Saving home: https://easyjethro.com.au/demo//?view=home
  Saving families: https://easyjethro.com.au/demo//?view=families
  ... (46 admin pages saved)

Done.
```

Snapshots land in `saved/` as `.html` files (or `.json` for binary/status endpoints).

## Example: `--testall` output

```
  PASS  public_check_in
  PASS  login
  PASS  public_home
  PASS  public_rosters
  PASS  public_rosters_view_3
  PASS  public_roster_roles
  PASS  public_rosters_view_2
  PASS  home
  PASS  public_rosters_view_1
  PASS  persons_reports
  PASS  families
  PASS  persons
  PASS  families_contact_list
  FAIL  families_list_all: response differs from saved snapshot
        vimdiff saved/families_list_all.html saved/families_list_all.actual.html
  PASS  public_roster_role_2
  PASS  public_roster_role_6
  PASS  groups
  PASS  persons_statistics
  ... (57 more PASSes)

1 test(s) failed: families_list_all
```

The `families_list_all` failure on the demo instance is a known bug tracked in [issue #1436](https://github.com/tbar0970/jethro-pmm/issues/1436) and is not caused by the test infrastructure.

When a test fails, the script writes the actual response to `saved/<id>.actual.html` and prints a ready-to-run `vimdiff` command for inspection.

## Known limitation: date-dependent output

Much of Jethro's data is inherently time-sensitive — roster assignments, upcoming service dates, attendance records, and similar content change day to day. This means snapshots saved today may legitimately differ from responses fetched tomorrow, producing spurious failures unrelated to code changes. A future improvement will address this by hardcoding Jethro's internal timestamps so that test output is stable across runs.
