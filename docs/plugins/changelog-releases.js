/**
 * Markdown preprocessor that assembles the changelog's per-release sections
 * from fragment files, so that branches adding changelog entries each add their
 * own file instead of editing a shared list in changelog/index.mdx (which
 * conflicts on every multi-branch merge).
 *
 * In any `changelog/index.mdx`, the marker line `{/* releases *\/}` is replaced
 * by one section per subdirectory of the sibling `_releases/` directory, newest
 * version first. For `_releases/<version>/`:
 *
 *  1. a generated `## Version <version>` heading, suffixed `(unreleased)` while
 *     the version is not yet released according to JETHRO_VERSION in
 *     devbox.json (see isUnreleased());
 *  2. `index.mdx`        - optional introduction;
 *  3. `<slug>.mdx`       - one feature's notes (usually one `###` section),
 *                          in filename order;
 *  4. `<slug>.upgrade.mdx` - that feature's upgrade notes (`####` sections),
 *                          in filename order, under one generated
 *                          `### <version> Upgrade Notes` heading.
 *
 * Every fragment except `index.mdx` starts with front matter declaring
 * whether the change needs SQL applied (an upgrades/*.sql script, or the
 * installer for new sites) to function:
 *
 *     ---
 *     requires_db_upgrade: true
 *     ---
 *
 * parseFragment() validates and strips it; the page does not show it. A
 * fragment may consist of front matter only (a change with no user-facing
 * note), and then renders nothing.
 *
 * Fragments are inlined as text before MDX parsing, so relative links and MDX
 * `import`s in a fragment are written as if they were in changelog/index.mdx.
 * `_releases/` is not published as pages: it is listed in the docs `exclude`
 * option in docusaurus.config.ts.
 *
 * Fragment edits are not watched by `docusaurus start`; save index.mdx to
 * re-render.
 *
 * @see docs/docs/developer/reference/changelog-releases.md
 */
const fs = require('fs');
const path = require('path');

const MARKER = '{/* releases */}';
const DEVBOX_JSON = path.resolve(__dirname, '..', '..', 'devbox.json');

/**
 * The development version from devbox.json's `env.JETHRO_VERSION` (e.g.
 * `2.40.0-DEV`), which devbox.d/bin/jethro_release also builds releases from.
 *
 * @returns {{version: string, isDev: boolean}} version without `-DEV`, and
 *     whether the `-DEV` suffix was present
 */
function jethroVersion() {
  const raw = JSON.parse(fs.readFileSync(DEVBOX_JSON, 'utf8')).env?.JETHRO_VERSION;
  if (typeof raw !== 'string' || !/^\d+(\.\d+)*(-DEV)?$/.test(raw)) {
    throw new Error(`changelog-releases: ${DEVBOX_JSON} env.JETHRO_VERSION must look like 2.40.0 or 2.40.0-DEV, got ${JSON.stringify(raw)}`);
  }
  return {version: raw.replace(/-DEV$/, ''), isDev: raw.endsWith('-DEV')};
}

/**
 * Compare two dotted version strings numerically, newest first.
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
function compareVersionsDesc(a, b) {
  const pa = a.split('.').map(Number);
  const pb = b.split('.').map(Number);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pb[i] || 0) - (pa[i] || 0);
    if (d !== 0) return d;
  }
  return 0;
}

/**
 * A release is unreleased if it is newer than JETHRO_VERSION, or equal to a
 * `-DEV` JETHRO_VERSION. Bumping JETHRO_VERSION to the next `-DEV` after a
 * release therefore marks that release as released.
 *
 * @param {string} version a `_releases/` directory name
 * @param {{version: string, isDev: boolean}} current from jethroVersion()
 * @returns {boolean}
 */
function isUnreleased(version, current) {
  const cmp = compareVersionsDesc(version, current.version);
  return cmp < 0 || (cmp === 0 && current.isDev);
}

/** Front matter keys a fragment may declare, and which are required. */
const FRONT_MATTER_KEYS = {requires_db_upgrade: {required: true}};

/**
 * Read a fragment: validate and strip its front matter.
 *
 * @param {string} file
 * @param {boolean} requireMetadata false for a release's index.mdx, which
 *        introduces the release rather than describing one change
 * @returns {{body: string, requiresDbUpgrade: boolean|null}} body with exactly
 *          one trailing newline, or '' when the fragment is front matter only;
 *          requiresDbUpgrade null when not declared (index.mdx only)
 */
function parseFragment(file, requireMetadata = true) {
  let text = fs.readFileSync(file, 'utf8');
  const meta = {};
  const fm = /^---\r?\n([\s\S]*?)\r?\n---[ \t]*(?:\r?\n|$)/.exec(text);
  if (fm) {
    for (const line of fm[1].split(/\r?\n/)) {
      if (/^\s*(#.*)?$/.test(line)) continue;
      const m = /^([a-z_]+):\s*(\S+)\s*$/.exec(line);
      if (!m || !(m[1] in FRONT_MATTER_KEYS)) {
        throw new Error(`changelog-releases: ${file}: unknown front matter line "${line}"`);
      }
      if (m[2] !== 'true' && m[2] !== 'false') {
        throw new Error(`changelog-releases: ${file}: ${m[1]} must be true or false, not "${m[2]}"`);
      }
      meta[m[1]] = m[2] === 'true';
    }
    text = text.slice(fm[0].length);
  }
  if (requireMetadata) {
    for (const [key, {required}] of Object.entries(FRONT_MATTER_KEYS)) {
      if (required && !(key in meta)) {
        throw new Error(`changelog-releases: ${file}: front matter must declare ${key}: true|false `
          + '(see docs/docs/developer/reference/changelog-releases.md)');
      }
    }
  }
  const body = text.trim() === '' ? '' : text.replace(/^\s*\n/, '').replace(/\s*$/, '\n');
  return {body, requiresDbUpgrade: meta.requires_db_upgrade ?? null};
}

/**
 * Render one `_releases/<version>/` directory as Markdown.
 *
 * @param {string} dir
 * @param {string} version
 * @param {{version: string, isDev: boolean}} current from jethroVersion()
 * @returns {string}
 */
function renderRelease(dir, version, current) {
  if (!/^\d+(\.\d+)*$/.test(version)) {
    throw new Error(`changelog-releases: ${dir} is not named after a version such as 2.40.0`);
  }
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.mdx')).sort();
  const read = f => parseFragment(path.join(dir, f), f !== 'index.mdx').body;
  const upgrades = files.filter(f => f.endsWith('.upgrade.mdx')).map(read).filter(b => b !== '');
  const features = files.filter(f => f !== 'index.mdx' && !f.endsWith('.upgrade.mdx')).map(read).filter(b => b !== '');

  const parts = [`## Version ${version}${isUnreleased(version, current) ? ' (unreleased)' : ''}\n`];
  if (files.includes('index.mdx')) parts.push(read('index.mdx'));
  parts.push(...features);
  if (upgrades.length > 0) {
    parts.push(`### ${version} Upgrade Notes\n`);
    parts.push(...upgrades);
  }
  return parts.join('\n');
}

/**
 * Docusaurus `markdown.preprocessor`: expand the releases marker in
 * changelog/index.mdx; return every other file unchanged.
 *
 * @param {{filePath: string, fileContent: string}} args
 * @returns {string}
 */
function expandChangelogReleases({filePath, fileContent}) {
  if (path.basename(filePath) !== 'index.mdx' || path.basename(path.dirname(filePath)) !== 'changelog'
      || !fileContent.includes(MARKER)) {
    return fileContent;
  }
  const current = jethroVersion();
  const releasesDir = path.join(path.dirname(filePath), '_releases');
  const versions = fs.readdirSync(releasesDir, {withFileTypes: true})
    .filter(d => d.isDirectory())
    .map(d => d.name)
    .sort(compareVersionsDesc);
  const rendered = versions.map(v => renderRelease(path.join(releasesDir, v), v, current)).join('\n');
  return fileContent.replace(MARKER, rendered);
}

module.exports = {expandChangelogReleases, jethroVersion, parseFragment};
