---
title: Changelog release fragments
description: How the What's New page is assembled from per-feature files under docs/docs/changelog/_releases/.
---

# Changelog release fragments

The [What's New](../../changelog/index.mdx) page (`docs/docs/changelog/index.mdx`) does not contain the notes for
recent releases directly. Each feature branch adds its own file under `docs/docs/changelog/_releases/<version>/`, and
the docs build inserts those files into the page. Branches therefore never edit the same lines, so merging several
feature branches does not conflict on the changelog.

## Layout

```
docs/docs/changelog/
├── index.mdx                       # page; {/* releases */} marks where releases go
└── _releases/
    └── 2.40.0/
        ├── index.mdx               # optional introduction for the release
        ├── sms.mdx                 # one feature: usually a single "### ..." section
        ├── sms.upgrade.mdx         # that feature's upgrade notes: "#### ..." sections
        └── user-accounts.mdx
```

For each version directory, newest version first, the page gets:

1. a generated `## Version <version>` heading, with ` (unreleased)` appended while the version is not yet released
   (see [Releasing](#releasing));
2. `index.mdx`, if present;
3. every other `*.mdx` file except `*.upgrade.mdx`, in filename order;
4. if any `*.upgrade.mdx` files exist, a generated `### <version> Upgrade Notes` heading followed by them, in filename
   order.

Name fragments after the feature (`member-directory-sharing.mdx`), not the issue number, so the filename order is
readable. Prefix a name (e.g. `00-sms.mdx`) only to move a headline feature to the top.

## Writing a fragment

- Fragments are inlined as text before MDX parsing, so write relative links and `import` statements as if the text
  were in `changelog/index.mdx`: `../user-manual/sms.mdx`, not `../../../user-manual/sms.mdx`.
- MDX `import` names share one module scope across all fragments; give them feature-specific names.
- Every fragment except a release's `index.mdx` starts with front matter saying whether the change needs SQL applied
  to function (see below). The build fails if it is missing or invalid.

## Database upgrade metadata

```mdx
---
requires_db_upgrade: true
---

### Feature heading
```

`requires_db_upgrade` is `true` when the change's code does not work until SQL has been applied: an
`upgrades/*.sql` (or `.php`) script on existing sites, or `include/installer.class.php` on new ones. Typical cases are
new tables, columns, views or stored functions that the code reads or writes. It is `false` when the change has no
database change, or only one the code can do without, e.g. new `setting` rows whose values the code also defaults
with `ifdef()`.

It is the only key allowed (`parseFragment()` in `docs/plugins/changelog-releases.js` rejects others), and it is not
shown on the page. Set it on each of a change's fragments, including `*.upgrade.mdx`. A change with nothing to tell
users still gets a fragment holding only the front matter; it renders nothing.

## Releasing

Nothing in `_releases/` changes at release time. Whether a version is shown as `(unreleased)` comes from
`env.JETHRO_VERSION` in `devbox.json`, the same value `devbox.d/bin/jethro_release` builds from:

| `JETHRO_VERSION` ↓ / `_releases/` directory → | `2.39.0` | `2.40.0` | `2.41.0` |
|---|---|---|---|
| `2.40.0-DEV` | released | unreleased | unreleased |
| `2.40.0` | released | released | unreleased |
| `2.41.0-DEV` | released | released | unreleased |

A release is built from `2.40.0-DEV` with `-DEV` stripped, so 2.40.0 is shown as unreleased until `JETHRO_VERSION` is
bumped to `2.41.0-DEV` after the release. The docs version dropdown label (`2.40.0-dev`) and the site-wide
"unreleased" banner in `docs/docusaurus.config.ts` follow the same value. Create the next version's directory when its
first change lands.

## Implementation

- `docs/plugins/changelog-releases.js` (`expandChangelogReleases`) is registered as `markdown.preprocessor` in
  `docs/docusaurus.config.ts`. It only rewrites files named `changelog/index.mdx` that contain the marker.
- `_releases/` is not published as separate pages because `docs/docusaurus.config.ts` lists
  `**/changelog/_releases/**` in the docs `exclude` option. (That option overrides Docusaurus's default `_`-partial
  exclusions, so the leading `_` alone would not hide it.)
- `docusaurus start` does not watch fragment files; save `changelog/index.mdx` to re-render after editing one.
