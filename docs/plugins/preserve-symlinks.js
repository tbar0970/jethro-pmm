// @ts-check

/**
 * Symlinked doc directories (e.g. docs/developer/reference/sms -> ../../../../jethro-sms/docs/reference)
 * are shared as canonical source between the docs site and the jethro-sms package.
 * Webpack's default `resolve.symlinks: true` collapses a symlinked module request to its
 * real path, which desyncs from the content-docs plugin's route/metadata registry (keyed by
 * the logical, symlinked path) and crashes DocItem with "Cannot read properties of undefined
 * (reading 'id')" for every doc under the symlinked directory. Disabling symlink resolution
 * keeps webpack module identity aligned with the logical docs path.
 *
 * @returns {import('@docusaurus/types').Plugin}
 */
module.exports = function preserveSymlinksPlugin() {
  return {
    name: 'preserve-symlinks',
    configureWebpack() {
      return {
        resolve: {
          symlinks: false,
        },
      };
    },
  };
};
