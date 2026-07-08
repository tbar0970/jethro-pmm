/**
 * Single source of truth for jethrosettings.json data.
 *
 * Used by React components (browser) via initData() after webpack-importing
 * the JSON5 file, and by Docusaurus plugins (Node.js) which call initData()
 * after reading the file from disk with fs + JSON5.
 *
 * Call initData(data) once before calling getData().
 */

let _data = null;

/**
 * Initialise with pre-loaded data.
 *
 * @param {object} data — parsed jethrosettings.json object
 * @returns {object} the stored data
 */
function initData(data) {
  _data = data;
  return _data;
}

/**
 * Returns the cached jethrosettings.json data.  Throws if not yet initialised.
 *
 * @returns {object}
 */
function getData() {
  if (!_data) throw new Error('settingsData not initialised — call initData() first');
  return _data;
}

/**
 * Resolves a group key, category key, or setting ID to a flat array of
 * setting IDs.  Categories expand recursively through their member groups.
 * Bare setting IDs pass through unchanged.
 *
 * @param {object} data — parsed jethrosettings.json object
 * @param {string|string[]} keys — group/category key(s) or setting IDs
 * @returns {string[]} flat list of setting IDs
 */
function resolveGroup(data, keys) {
  const categories = data.categories || {};
  const groups = data.groups || {};
  const settings = data.settings || {};
  const list = Array.isArray(keys) ? keys : [keys];
  const result = [];
  for (const key of list) {
    if (categories[key]) {
      for (const gk of categories[key].groups) {
        result.push(...resolveGroup(data, gk));
      }
    } else if (groups[key]) {
      result.push(...groups[key].settings);
    } else if (settings[key]) {
      result.push(key);
    }
  }
  return result;
}

/**
 * Returns all setting IDs defined in the settings object.
 *
 * @param {object} data — parsed jethrosettings.json object
 * @returns {string[]}
 */
function getAllSettingIds(data) {
  return Object.keys(data.settings || {});
}

module.exports = { initData, getData, resolveGroup, getAllSettingIds };
