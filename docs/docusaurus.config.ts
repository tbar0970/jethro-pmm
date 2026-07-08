import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';

const config: Config = {
  title: 'Jethro ChMS',
  tagline: 'Pastoral Ministry Manager — Church Management Software',
  favicon: 'img/favicon.ico',

  future: { v4: true },

  url: 'https://jethro-chms.github.io',
  baseUrl: '/docs/',

  organizationName: 'jethro-chms',
  projectName: 'jethro',
  trailingSlash: 'false',

  onBrokenLinks: 'throw',


  plugins: [require.resolve('docusaurus-plugin-image-zoom'), require.resolve('./plugins/validate-settings-sync'), require.resolve('./plugins/validate-settings-documented'), require.resolve('./plugins/preserve-symlinks')],

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  // Mermaid (@docusaurus/theme-mermaid) was removed because webpack/Rspack
  // cannot resolve `export *` re-export chains through d3's ESM source tree,
  // causing "curveBumpX/curveBumpY not exported from d3" and "blur2 not
  // exported from d3-array" at build time (mermaid 11.16 + d3 7.9).
  // The single ER diagram in jethro-sms/docs/reference/database.mdx was
  // replaced with ASCII art. To re-enable mermaid in future, test that
  // `bun run build` succeeds after re-adding the theme, the `markdown.mermaid`
  // config, and `@docusaurus/theme-mermaid` to dependencies.


  /*
  markdown: {
    mermaid: true,
  },
  themes: ['@docusaurus/theme-mermaid'],
  */
  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          editUrl: 'https://github.com/jethro-chms/jethro/edit/main/docs/',
          lastVersion: 'current',
          exclude: ['**/jethrosettings.json'],
          rehypePlugins: [
            [require('./plugins/fix-root-relative-paths'), { baseUrl: '/docs/' }],
          ],
          versions: {
            current: {
              label: '2.39.0-dev',
              path: '2.39.0-dev',
              banner: 'unreleased',
            },
          },
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      },
    ],
  ],

  themeConfig: {
    zoom: {
      selector: '.markdown :not(em) img',
    },
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'Church Management',
      logo: {
        alt: 'Jethro Logo',
        src: 'img/jethro-black.png',
      },
      items: [
        { type: 'doc', docId: 'overview', label: 'Overview', position: 'left' },
        { type: 'doc', docId: 'installation/index', position: 'left', label: 'Install', },
        { type: 'docSidebar', sidebarId: 'userManual', position: 'left', label: 'User Manual', },
        { type: 'docSidebar', sidebarId: 'administration', position: 'left', label: 'Administration', },
        { type: 'doc', docId: 'changelog/index', label: "What's New", position: 'left' },
        { type: 'docSidebar', sidebarId: 'developer', position: 'left', label: 'Developer', },
        { href: 'https://easyjethro.com.au', label: 'Easy Jethro', position: 'left' },
        { type: 'docsVersionDropdown', position: 'right' },
      ],
    },
    footer: {
      style: 'dark',
      links: [],
      copyright: `Copyright © ${new Date().getFullYear()} Jethro ChMS. Built with Docusaurus.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'json', 'sql'],
    },
  },
};

export default config;
