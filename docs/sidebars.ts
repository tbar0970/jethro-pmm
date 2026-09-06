import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  overview: [
    'overview',
  ],

  installation: [
    {
      type: 'category',
      label: 'Installation',
      link: { type: 'doc', id: 'installation/index' },
      items: [
        'installation/index',
      ],
    },
  ],

  administration: [
    {
      type: 'category',
      label: 'Administration',
      link: { type: 'doc', id: 'administration/config/index' },
      items: [
        'administration/congregations',
        'administration/user-accounts',
        'administration/permissions',
        'administration/note-templates',
        'administration/action-plans',
        'administration/config/index',
        'administration/import',
      ],
    },
  ],

  userManual: [
    {
      type: 'category',
      label: 'User Manual',
      link: { type: 'doc', id: 'user-manual/getting-started' },
      items: [
        'user-manual/getting-started',
      ],
    },
  ],

  whatsNew: [
    'changelog/index',
  ],


  developer: [
    {
      type: 'category',
      label: 'Developer',
      items: [
        'developer/DEVELOPMENT_DEVBOX',
        'developer/developer_tips',
        'contributing',
        'developer/reference/view-menu-convention',
      ],
    },
  ],

};

export default sidebars;
