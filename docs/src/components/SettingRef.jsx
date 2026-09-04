import data from '../../jethrosettings.json';
import { getData, initData } from '../settingsData';

initData(data);


function stripHtml(html) {
  return html?.replace(/<[^>]*>/g, '') ?? '';
}

function formatName(name) {
  return name
    .split('_')
    .map(w => w.charAt(0) + w.slice(1).toLowerCase())
    .join(' ');
}

export default function SettingRef({ name }) {
  const setting = getData().settings[name];
  if (!setting) {
    throw new Error(`SettingRef: unknown setting "${name}" — valid settings are in docs/jethrosettings.json`);
  }
  const href = `../administration/config/sms#${name}`;
  return <a href={href} title={stripHtml(setting.description)}><span class="settingRef">{formatName(name)}</span></a>;
}
