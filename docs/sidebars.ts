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
        {
          type: 'category',
          label: 'System Configuration',
          link: { type: 'doc', id: 'administration/config/index' },
          items: [
            'administration/config/sms',
          ],
        },
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
        'user-manual/sms',
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
        'developer/reference/import',
        'contributing',
        'developer/DEVELOPMENT_FUNCTESTS',
        {
          type: 'category',
          label: 'SMS Reference',
          items: [
            'developer/sms/smsarchitecture',
            'developer/sms/send-pipeline',
            'developer/sms/engine/providers',
            'developer/sms/engine/provider-abstraction',
            'developer/sms/engine/configuration',
            'developer/sms/database',
            'developer/sms/file-layout',
            'developer/sms/engine/status-codes',
            'developer/sms/engine/token-expansion',
            'developer/sms/engine/character-counting',
            'developer/sms/delivery-tracking',
            'developer/sms/SMS_DATASTAR',
            'developer/sms/history-sync',
            'developer/sms/design-decisions',
            'developer/sms/engine/api-reference',
          ],
        },
      ],
    },
  ],

};

export default sidebars;
