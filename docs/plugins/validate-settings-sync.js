/**
 * Validates that every SMS_* constant in the working copy's conf.php.sample
 * has a corresponding entry in jethrosettings.json.
 *
 * Versioned snapshots under versioned_docs/ are not checked — they are
 * historical artifacts that may intentionally differ.
 *
 * Skips SMS_2FA_* (authentication, not SMS gateway) and SMS_HTTP_ (prefix).
 */
const fs = require('fs');
const path = require('path');
const { initData } = require('../src/settingsData');

module.exports = function validateSettingsJsonPlugin(_context, _options) {
  return {
    name: 'validate-settings-sync',
    async loadContent() {
      const repoRoot = path.resolve(__dirname, '..', '..');
      const samplePath = path.join(repoRoot, 'conf.php.sample');

      const sample = fs.readFileSync(samplePath, 'utf8');
      const jsonPath = path.join(repoRoot, 'docs', 'jethrosettings.json');
      const data = initData(JSON.parse(fs.readFileSync(jsonPath, 'utf8')));
      const smsRegex = /\bSMS_[A-Z0-9_]+\b/g;
      const sampleSet = new Set(sample.match(smsRegex) || []);

      const skip = new Set(['SMS_HTTP_']);
      for (const s of sampleSet) {
        if (s.startsWith('SMS_2FA_')) skip.add(s);
      }
      for (const s of skip) sampleSet.delete(s);

      const jsonSet = new Set(
        Object.keys(data.settings).filter(k => k.startsWith('SMS_'))
      );

      const missing = [...sampleSet].filter(s => !jsonSet.has(s)).sort();
      const extras = [...jsonSet].filter(s => !sampleSet.has(s)).sort();

      if (missing.length > 0) {
        throw new Error(
          'jethrosettings.json is missing SMS settings found in conf.php.sample:\n' +
          '  ' + missing.join('\n  ') +
          '\n\nAdd them to docs/jethrosettings.json'
        );
      }

      if (extras.length > 0) {
        console.warn(
          '⚠ jethrosettings.json has SMS settings NOT in conf.php.sample:\n' +
          '  ' + extras.join('\n  ') +
          '\n  (UI-only settings are fine; this is FYI)'
        );
      }
      console.log('✅ jethrosettings.json SMS settings match conf.php.sample');
    },
  };
};
