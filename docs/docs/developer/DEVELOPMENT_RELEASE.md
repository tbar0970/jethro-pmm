---
sidebar_position: 10
---

# Making a release

A Jethro release is a `jethro-$VER.zip` archive built from a git tag. It
contains the application source plus the compiled JS/CSS bundles and the
Composer `vendor/` dependencies (which are not stored in git). The archive is
published on GitHub as a Release, together with a `.sha256` checksum.

Assuming you are releasing version `$VER` (e.g. `2.40.0`):

## The short version

1. Tag the commit you want to release and push it:

   ```sh
   git tag v$VER
   git push --tags
   ```

2. Run the **Build Release** workflow on GitHub Actions (it is triggered
   manually). In the "Run workflow" form, pick the tag and enter `$VER` as the
   version.

3. The workflow builds `jethro-$VER.zip`, writes its `sha256`, and attaches
   both to a **draft** GitHub Release for the tag.

4. Review the draft on the repository's Releases page, edit the notes, and
   click **Publish release**.

## Building locally

The same build can be run from a checkout of the tagged commit, without GitHub:

```sh
./devbox run release   # Version derived from $JETHRO_VERSION in devbox.json
```
or if git is not yet tagged:

```sh
./devbox run release --allow-untagged   # Version derived from $JETHRO_VERSION in devbox.json
```

The `--allow-untagged` flag skips the git-tag check, so you don't need to tag
the commit first. The resulting archive is a test build only — it is not
suitable for release.

## What the build does

`devbox.d/bin/jethro_release`:

1. refuses to run unless `HEAD` is a git tag matching the version (a leading
   `v` is allowed) and the version does not contain `DEV` — unless
   `--allow-untagged` is passed, which skips the tag check for test builds;
2. compiles the JS/CSS bundles and stamps `include/version.txt`
   (`devbox.d/bin/jethro_compile`);
3. runs `composer install --no-dev --optimize-autoloader` to fetch `vendor/`;
4. assembles the archive from `git archive` (which honours `.gitattributes`
   `export-ignore`, so dev-only files are left out) and overlays the
   freshly-built `vendor/` and compiled assets;
5. writes `jethro-$VER.zip` and a `sha256` checksum.

## Notes

- The tag must match the version: `v$VER` or `$VER` both work.
- The version can be passed as a positional argument (which overrides the
  `$JETHRO_VERSION` environment variable) or taken from `JETHRO_VERSION`, with
  any trailing `-DEV` stripped (so `2.40.0-DEV` in `devbox.json` builds `2.40.0`).
- The list of paths excluded from the archive lives in `.gitattributes`
  (`export-ignore`). `vendor/` is gitignored, not `export-ignore`d — it is
  rebuilt by Composer at build time.
- A release can only be built from a tagged commit unless `--allow-untagged` is
  used for test builds.
