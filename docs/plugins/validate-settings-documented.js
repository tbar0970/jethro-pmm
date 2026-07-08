/**
 * Validates that every setting in jethrosettings.json is documented on
 * administration/config/index.mdx — either directly via SingleSetting /
 * MultiSettings, or indirectly via MultiSettings group= references,
 * or via the imported sms.mdx for SMS_* settings.
 *
 * Settings appearing only in settings-glossary.mdx (reference tables) are
 * NOT considered documented on the config page.
 */
const fs = require('fs');
const path = require('path');
const { initData, resolveGroup, getAllSettingIds } = require('../src/settingsData');

module.exports = function validateSettingsDocumentedPlugin(_context, _options) {
  return {
    name: 'validate-settings-documented',
    async loadContent() {
      const repoRoot = path.resolve(__dirname, '..', '..');
      const jsonPath = path.join(repoRoot, 'docs', 'jethrosettings.json');
      const indexPath = path.join(repoRoot, 'docs', 'docs', 'administration', 'config', 'index.mdx');

      const data = initData(JSON.parse(fs.readFileSync(jsonPath, 'utf8')));
      const allSettingIds = new Set(getAllSettingIds(data));

      const indexContent = fs.readFileSync(indexPath, 'utf8');

      // Extract settings referenced via SingleSetting id="X"
      const idRegex = /\b(?:SingleSetting)\s+id\s*=\s*["']([A-Z0-9][A-Z0-9_]*)["']/g;
      const documentedIds = new Set();

      let m;
      while ((m = idRegex.exec(indexContent)) !== null) {
        documentedIds.add(m[1]);
      }

      // Extract settings referenced via MultiSettings ids="X,Y,Z"
      const idsRegex = /\b(?:MultiSettings)\s+ids\s*=\s*["']([A-Z0-9][A-Z0-9_]*(?:\s*,\s*[A-Z0-9][A-Z0-9_]*)*)["']/g;
      while ((m = idsRegex.exec(indexContent)) !== null) {
        m[1].split(',').map(s => s.trim()).forEach(id => documentedIds.add(id));
      }

      // Extract settings referenced via MultiSettings group="key" — resolve via library
      const groupRegex = /\b(?:MultiSettings)\s+group\s*=\s*["']([a-zA-Z][a-zA-Z0-9]*)["']/g;
      while ((m = groupRegex.exec(indexContent)) !== null) {
        const resolved = resolveGroup(data, m[1]);
        resolved.forEach(id => documentedIds.add(id));
      }

      // SMS settings should be documented via sms.mdx imported into index.mdx.
      const smsSettings = new Set();
      for (const id of allSettingIds) {
        if (id.startsWith('SMS_') || id.startsWith('2FA_SMS_')) smsSettings.add(id);
      }

      const missing = [...allSettingIds]
        .filter(id => !documentedIds.has(id) && !smsSettings.has(id))
        .sort();

      if (missing.length > 0) {
        throw new Error(
          'jethrosettings.json has settings not documented on config/index.mdx:\n' +
          '  ' + missing.join('\n  ') +
          '\n\nAdd them to docs/docs/administration/config/index.mdx\n' +
          'via SingleSetting, MultiSettings ids=, MultiSettings group=, or ensure sms.mdx covers them.'
        );
      }

      const n = documentedIds.size;
      console.log(`✅ All ${allSettingIds.size} jethrosettings.json entries accounted for (${n} settings documented directly or via group=)`);
    },
  };
};
