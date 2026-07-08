// @ts-check

const { visit } = require('unist-util-visit');

/**
 * Rehype plugin that rewrites root-relative `src` / `href` / `poster`
 * attributes in HTML elements to include `baseUrl`.  Raw HTML in MDX
 * (e.g. `<img src="/img/foo.png">`) is emitted verbatim by Docusaurus
 * and breaks under a non-root baseUrl.
 *
 * @param {{ baseUrl: string }} options
 * @returns {import('unified').Transformer}
 */
module.exports = function rehypeFixRootRelativePaths(options = { baseUrl: '/' }) {
  const baseUrl = options.baseUrl.replace(/\/$/, ''); // strip trailing slash

  return (tree) => {
    visit(tree, 'element', (node) => {
      const props = node.properties;
      if (!props) return;

      for (const attr of ['src', 'href', 'poster']) {
        const val = props[attr];
        if (typeof val === 'string' && val.startsWith('/') && !val.startsWith(baseUrl + '/')) {
          props[attr] = baseUrl + val;
        }
      }
    });
  };
};
