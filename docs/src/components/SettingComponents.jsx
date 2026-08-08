import React from 'react';
import CodeBlock from '@theme/CodeBlock';
import data from '../../jethrosettings.json';
import { initData, resolveGroup } from '../settingsData';

initData(data);

const OPTIONS = data.options;
/**
 * Lookup table: setting ID → {description, explanation, default}.
 *
 * Built by iterating `data.settings` from jethrosettings.json.  The `default`
 * field is stringified; `null`/`undefined` defaults become an em-dash
 * placeholder so the UI and glossary table can display "—" consistently.
 *
 * @type {Record<string, {description: string, explanation: string, default: string}>}
 */
const setting_help = {};
for (const [id, s] of Object.entries(data.settings)) {
  setting_help[id] = {
    description: s.description,
    explanation: s.explanation || '',
    note: s.note || '',
    default: s.default === null || s.default === undefined ? '\u2014' : String(s.default),
  };
}

/**
 * Normalize a raw JSON setting definition into the internal config shape
 * consumed by {@link SettingRow} and the {@link TYPE_MAP} components.
 *
 * - Resolves `options` key names (e.g. `"boolean"`, `"unicode"`, `"provider"`)
 *   to the actual option arrays from `data.options`.
 * - Only includes `defaultValue` when the JSON defines a non-null default.
 * - Only includes `fileOnly` and `alert` when truthy (keeps config objects lean).
 *
 * @param {object} s — raw setting entry from `data.settings`
 * @param {string} s.id          — e.g. `"SMS_SENDER"`
 * @param {string} s.type        — component type key, e.g. `"text"`, `"select"`, `"none"`
 * @param {string} s.label       — UI label, e.g. `"Sms Sender"`
 * @param {*}      s.default     — default value (may be null)
 * @param {string} [s.options]   — key into `data.options`, e.g. `"boolean"`
 * @param {boolean} [s.fileOnly] — true if the setting is conf.php–only
 * @param {string} [s.alert]     — warning text for locked configs
 * @returns {{type: string, id: string, label: string, defaultValue?: *, options?: Array<{value: string, label: string}>, fileOnly?: boolean, alert?: string}}
 *
 * @example
 * // From jethrosettings.json: SMS_SENDER (no default, no options)
 * makeConfig({
 *   id: "SMS_SENDER", type: "text", label: "Sms Sender",
 *   description: "Hardcoded sender ID…", default: null
 * });
 * // → { type: "text", id: "SMS_SENDER", label: "Sms Sender" }
 *
 * @example
 * // From jethrosettings.json: SMS_UNICODE_PERMITTED (has options key, has default)
 * makeConfig({
 *   id: "SMS_UNICODE_PERMITTED", type: "select", label: "Sms Unicode Permitted",
 *   default: "when_free", options: "unicode", …
 * });
 * // → { type: "select", id: "SMS_UNICODE_PERMITTED",
 * //     label: "Sms Unicode Permitted", defaultValue: "when_free",
 * //     options: [{value: "when_free", label: "When it costs nothing extra"}, …] }
 *
 * @example
 * // From jethrosettings.json: SMS_SEND_LOGFILE (fileOnly + alert)
 * makeConfig({
 *   id: "SMS_SEND_LOGFILE", type: "configLocked", label: "Sms Send Logfile",
 *   default: null, fileOnly: true, alert: "", …
 * });
 * // → { type: "configLocked", id: "SMS_SEND_LOGFILE",
 * //     label: "Sms Send Logfile", fileOnly: true, alert: "" }
 */
function makeConfig({ id, type, label, default: def, options, fileOnly, alert }) {
  const config = { type, id, label };
  if (def !== undefined && def !== null) config.defaultValue = def;
  if (options) config.options = OPTIONS[options];
  if (fileOnly) config.fileOnly = true;
  if (alert) config.alert = alert;
  return config;
}

export const SETTINGS = {};
for (const [id, s] of Object.entries(data.settings)) {
  SETTINGS[id] = makeConfig({ id, ...s });
}

// ---------------------------------------------------------------------------
// Parameterized primitives
// ---------------------------------------------------------------------------

/**
 * Renders a `<label>` linked to its form control via `htmlFor`.
 *
 * @param {object} props
 * @param {string} props.id    — the `id` of the associated input
 * @param {string} props.label — visible label text
 *
 * @example
 * // Renders: <label class="control-label" for="SMS_SENDER">Sms Sender</label>
 * <InfoLabel id="SMS_SENDER" label="Sms Sender" />
 */
function InfoLabel({ id, label }) {
  return (
    <label className="control-label" htmlFor={id}>
      {label}
    </label>
  );
}

/**
 * Renders a clickable "help" / "hide" toggle that controls whether the
 * {@link HelpBox} for a setting is visible.
 *
 * Looks up `setting_help[id]`; returns `null` (renders nothing) when no help
 * entry exists, e.g. for settings not in the JSON or with empty descriptions.
 *
 * @param {object} props
 * @param {string}   props.id       — setting ID (e.g. `"SMS_MAX_LENGTH"`)
 * @param {boolean}  props.open     — whether the help box is currently shown
 * @param {Function} props.onToggle — callback to flip `open` state
 *
 * @example
 * // Renders "ℹ️ hide" when open, "ℹ️ help" when closed
 * <HelpToggle id="SMS_MAX_LENGTH" open={true} onToggle={() => setOpen(!open)} />
 */
function HelpToggle({ id, open, onToggle }) {
  const help = setting_help[id];
  if (!help) return null;
  return (
    <span className="help-toggle"
          onClick={onToggle}
          >
      &#9432;&#65039; {open ? 'hide' : 'help'}
    </span>
  );
}

/**
 * Renders the setting note inline, always visible above the help toggle
 * (unlike {@link HelpBox}, which is hidden by default).
 *
 * The note is a short plain-text summary from the database `setting.note`
 * column, distinct from the longer HTML `description`.
 *
 * Reads from `setting_help[id].note`; returns `null` when no note is
 * available.
 *
 * @param {object} props
 * @param {string} props.id — setting ID
 *
 * @example
 * // For SMS_TESTMODE, renders:
 * // "In Test Mode no SMSes are actually sent"
 * <SettingDescription id="SMS_TESTMODE" />
 */
function SettingDescription({ id }) {
  const help = setting_help[id];
  if (!help || !help.note) return null;
  return <div className="smallprint" >{help.note}</div>;
}

/**
 * Renders the expanded help detail for a setting: explanation, file-only
 * caveat, and default value.  The description is shown separately, always
 * visible, via {@link SettingDescription}.
 *
 * Returns `null` when there is nothing to show (no explanation, no non-em-dash
 * default, and not file-only).
 *
 * Content is assembled as an HTML string and injected via
 * `dangerouslySetInnerHTML` because explanations in `jethrosettings.json` already
 * contain HTML markup (`<code>`, `<br>`, `<a>`).
 *
 * @param {object} props
 * @param {string}  props.id       — setting ID
 * @param {boolean} props.fileOnly — whether to show "This setting can only be
 *                                   set on the server in conf.php."
 *
 * @example
 * // For SMS_MAX_LENGTH, renders an alert-info box containing:
 * //   "SMSes are billed per 160 characters…"
 * //   "Default: 160."
 * <HelpBox id="SMS_MAX_LENGTH" fileOnly={false} />
 *
 * @example
 * // For SMS_BALANCE (fileOnly), renders:
 * //   "This setting can only be set on the server in conf.php."
 * <HelpBox id="SMS_BALANCE" fileOnly={true} />
 */
function HelpBox({ id, fileOnly }) {
  const help = setting_help[id];
  if (!help) return null;
  return (
    <div className="alert alert-info" style={{marginTop: '4px', padding: '6px 10px', fontSize: '0.9em'}}>
      <div>{help.description}</div>
      {help.explanation && <div>{help.explanation}</div>}
      {fileOnly && <div><em>This setting can only be set on the server in <code>conf.php</code>.</em></div>}
      {help.default !== '\u2014' && <> Default: <code>{help.default}</code>.</>}
    </div>
  );
}

/**
 * Renders a text `<input>` with label, help toggle, and expandable help box.
 *
 * Intended for settings of type `"text"` in jethrosettings.json.
 *
 * @param {object} props
 * @param {string}  props.id           — setting ID (used as `name` and `id`)
 * @param {string}  props.label        — UI label
 * @param {string}  [props.defaultValue=""] — fallback value
 * @param {number}  [props.size=60]    — visible width in characters
 * @param {string}  [props.value]      — runtime override (from saved values)
 * @param {boolean} [props.fileOnly]   — forwarded to {@link HelpBox}
 *
 * @example
 * // Renders a text input for the sender ID, 60 chars wide
 * // See jethrosettings.json: SMS_SENDER (type "text", no default)
 * <SettingTextInput id="SMS_SENDER" label="Sms Sender" defaultValue="" size={60} />
 *
 * @example
 * // Settings using this component from jethrosettings.json:
 * //   SMS_SENDER, SMS_SENDER_OPTIONS, SMS_SENDER_DEFAULT,
 * //   SMS_SAVE_TO_NOTE_SUBJECT, SMS_5CENTSMS_APIKEY_ID, SMS_5CENTSMS_APIKEY
 */
function SettingTextInput({ id, label, defaultValue = '', size = 60, value, fileOnly }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        <input type="text" name={id} defaultValue={value ?? defaultValue} size={size} />
        <SettingDescription id={id} />
        <HelpToggle id={id} open={open} onToggle={() => setOpen(!open)} />
        {open && <HelpBox id={id} fileOnly={fileOnly} />}
      </div>
    </div>
  );
}

/**
 * Renders a numeric `<input>` with label, help toggle, and expandable help box.
 *
 * Intended for settings of type `"number"` in jethrosettings.json.
 *
 * @param {object} props
 * @param {string}  props.id           — setting ID
 * @param {string}  props.label        — UI label
 * @param {number}  [props.defaultValue] — fallback value
 * @param {number}  [props.size=5]     — visible width in characters
 * @param {string}  [props.value]      — runtime override
 * @param {boolean} [props.fileOnly]   — forwarded to {@link HelpBox}
 *
 * @example
 * // Renders a number input labeled "Sms Max Length", default 160
 * // See jethrosettings.json: SMS_MAX_LENGTH (type "number", default 160)
 * <SettingNumberInput id="SMS_MAX_LENGTH" label="Sms Max Length"
 *                     defaultValue={160} size={5} />
 *
 * @example
 * // Settings using this component from jethrosettings.json:
 * //   SMS_MAX_LENGTH (160), SMS_SEND_COOLOFF (30),
 * //   SMS_BALANCE_LOW_THRESHOLD (0)
 */
function SettingNumberInput({ id, label, defaultValue, size = 5, value, fileOnly }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        <input pattern="[0-9]*" inputMode="numeric" type="number" name={id} defaultValue={value ?? defaultValue} size={size} />
        <SettingDescription id={id} />
        <HelpToggle id={id} open={open} onToggle={() => setOpen(!open)} />
        {open && <HelpBox id={id} fileOnly={fileOnly} />}
      </div>
    </div>
  );
}

/**
 * Renders a `<select>` dropdown with label, help toggle, and expandable help box.
 *
 * Intended for settings of type `"select"` in jethrosettings.json.
 *
 * @param {object} props
 * @param {string}  props.id                        — setting ID
 * @param {string}  props.label                     — UI label
 * @param {string}  [props.defaultValue]            — fallback selected value
 * @param {Array<{value: string, label: string}>} props.options — dropdown choices
 * @param {string}  [props.value]                   — runtime override
 * @param {boolean} [props.fileOnly]                — forwarded to {@link HelpBox}
 *
 * @example
 * // Provider dropdown with "Auto-detect", "5CentSMS v5", "Cellcast", "SMS Broadcast"
 * // See jethrosettings.json: SMS_PROVIDER (type "select", options key "provider")
 * const opts = [{value: "", label: "Auto-detect (based on settings)"},
 *               {value: "5centsmsv5", label: "5CentSMS v5"}, …];
 * <SettingSelect id="SMS_PROVIDER" label="Sms Provider"
 *                defaultValue="" options={opts} />
 *
 * @example
 * // Yes/No dropdown for SMS_TESTMODE, default "0" (No)
 * // See jethrosettings.json options.boolean: [{value: "0", label: "No"}, {value: "1", label: "Yes"}]
 * const boolOpts = [{value: "0", label: "No"}, {value: "1", label: "Yes"}];
 * <SettingSelect id="SMS_TESTMODE" label="Sms Testmode"
 *                defaultValue="0" options={boolOpts} />
 *
 * @example
 * // Settings using this component from jethrosettings.json:
 * //   SMS_UNICODE_PERMITTED (options "unicode"), SMS_SHORTEN_URLS,
 * //   SMS_SAVE_TO_NOTE_BY_DEFAULT, SMS_TESTMODE, SMS_VERBOSE,
 * //   SMS_PROVIDER
 */
function SettingSelect({ id, label, defaultValue, options: opts, value, fileOnly }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        <select name={id} data-allow-empty="0" defaultValue={value ?? defaultValue}>
          {opts.map(o => (<option key={o.value} value={o.value}>{o.label}</option>))}
        </select>
        <SettingDescription id={id} />
        <HelpToggle id={id} open={open} onToggle={() => setOpen(!open)} />
        {open && <HelpBox id={id} fileOnly={fileOnly} />}
      </div>
    </div>
  );
}

/**
 * Renders a person-search autocomplete widget with a hidden input storing
 * the selected person ID, plus label, help toggle, and expandable help box.
 *
 * Intended for settings of type `"personSearch"` in jethrosettings.json.
 *
 * The visible text input is a `.person-search-single` autocomplete widget;
 * the hidden `<input>` named after the setting `id` carries the actual
 * person ID submitted with the form.
 *
 * @param {object} props
 * @param {string}  props.id           — setting ID
 * @param {string}  props.label        — UI label
 * @param {number}  [props.defaultValue=0] — fallback person ID
 * @param {string}  [props.value]      — runtime override
 * @param {boolean} [props.fileOnly]   — forwarded to {@link HelpBox}
 *
 * @example
 * // Renders a person search labeled "Sms Balance Low Notificant"
 * // See jethrosettings.json: SMS_BALANCE_LOW_NOTIFICANT (type "personSearch", default 0)
 * <SettingPersonSearch id="SMS_BALANCE_LOW_NOTIFICANT"
 *                      label="Sms Balance Low Notificant" defaultValue={0} />
 */
function SettingPersonSearch({ id, label, defaultValue = 0, value, fileOnly }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        <input type="text" placeholder="Search persons" id={`${id}-input`} className="person-search-single" data-show-absence-date="" />
        <input type="hidden" name={id} defaultValue={value ?? defaultValue} />
        <SettingDescription id={id} />
        <HelpToggle id={id} open={open} onToggle={() => setOpen(!open)} />
        {open && <HelpBox id={id} fileOnly={fileOnly} />}
      </div>
    </div>
  );
}

/**
 * Renders a read-only setting value with a warning alert, label, help toggle,
 * and expandable help box.
 *
 * Intended for settings of type `"configLocked"` in jethrosettings.json.
 *
 * The `alert` text is shown in an `alert-warning` box alongside the value.
 * Settings of this type typically also carry `fileOnly: true`.
 *
 * @param {object} props
 * @param {string}  props.id       — setting ID
 * @param {string}  props.label    — UI label
 * @param {string}  props.value    — the current (read-only) value to display
 * @param {string}  props.alert    — warning message shown below the value
 * @param {boolean} props.fileOnly — forwarded to {@link HelpBox}
 *
 * @example
 * // Renders "Sms Send Logfile" label, the logfile path as plain text,
 * // an alert-warning box, and the help toggle
 * // See jethrosettings.json: SMS_SEND_LOGFILE (type "configLocked", fileOnly: true)
 * <SettingConfigLocked id="SMS_SEND_LOGFILE" label="Sms Send Logfile"
 *                      value="/var/log/sms.json" alert="" fileOnly={true} />
 *
 * @example
 * // Settings using this component from jethrosettings.json:
 * //   SMS_SEND_LOGFILE, SMS_CELLCAST_APIKEY
 */
function SettingConfigLocked({ id, label, value, alert, fileOnly }) {
  const [open, setOpen] = React.useState(true);
  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        {value}
        <div className="alert alert-warning"><i className="icon-info-sign" /> {alert}</div>
        <SettingDescription id={id} />
        <HelpToggle id={id} open={open} onToggle={() => setOpen(!open)} />
        {open && <HelpBox id={id} fileOnly={fileOnly} />}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Map setting type → component
// ---------------------------------------------------------------------------


function SettingPhpDefine({ id, label, defaultValue, type }) {
  const help = setting_help[id];

  // Format value as a PHP literal
  let phpVal;
  if (defaultValue === undefined || defaultValue === null) {
    phpVal = "''";
  } else if (typeof defaultValue === 'boolean') {
    phpVal = defaultValue ? 'true' : 'false';
  } else if (type === 'number' || typeof defaultValue === 'number') {
    phpVal = String(defaultValue);
  } else {
    // string
    phpVal = "'" + String(defaultValue).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
  }

  // Strip HTML tags for comment output
  const stripHtml = (s) => s ? s.replace(/<[^>]*>/g, '') : '';

  // Build multi-line comment block from description and explanation
  let blockComment = '';
  const desc = stripHtml(help?.description);
  const expl = stripHtml(help?.explanation);
  if (desc) blockComment += '// ' + desc + '\n';
  if (expl) {
    for (const line of expl.split('\n')) {
      const trimmed = line.trim();
      if (trimmed) blockComment += '// ' + trimmed + '\n';
    }
  }

  // Inline comment from note
  const inline = help?.note ? ' // ' + stripHtml(help.note) : '';

  const code = blockComment + "define('" + id + "', " + phpVal + ");" + inline;

  return (
    <div className="control-group" id={id}>
      <InfoLabel id={id} label={label} />
      <div className="controls">
        <CodeBlock language="php">{code}</CodeBlock>
      </div>
    </div>
  );
}

/**
 * Maps setting <code>type</code> strings to their React component for
 * settings that are <em>not</em> <code>fileOnly</code>.
 *
 * Used by {@link SettingRow} to dispatch each setting definition to the
 * correct visual component.  <code>fileOnly</code> settings bypass this
 * map and are rendered as PHP <code>define()</code> blocks by
 * {@link SettingPhpDefine} regardless of their <code>type</code>.
 *
 * @type {Record<string, React.ComponentType>}
 */
const TYPE_MAP = {
  text: SettingTextInput,
  number: SettingNumberInput,
  select: SettingSelect,
  personSearch: SettingPersonSearch,
  configLocked: SettingConfigLocked,
};

/**
 * Dispatches a setting definition to the correct typed component
 * (via {@link TYPE_MAP}), applying runtime value overrides when provided.
 *
 * If the `values` prop contains an entry for this setting's `id`, that
 * override is spread onto the component props in place of the static
 * `defaultValue`.  This allows the form to show saved/existing values.
 *
 * Logs a warning and renders nothing when the `type` is unrecognised.
 *
 * @param {object} props
 * @param {string}  props.type   — setting type key (e.g. `"text"`, `"select"`)
 * @param {object}  [props.values] — runtime value overrides, keyed by setting ID
 * @param {...*}     restProps    — spread from `SETTINGS[id]`, forwarded to the
 *                                  resolved component (id, label, defaultValue, …)
 *
 * @example
 * // Dispatches SMS_TESTMODE ("select") → <SettingSelect … />
 * <SettingRow
 *   type="select" id="SMS_TESTMODE" label="Sms Testmode"
 *   defaultValue="0"
 *   options={[{value:"0",label:"No"}, {value:"1",label:"Yes"}]}
 *   values={{ SMS_TESTMODE: "1" }}
 * />
 * // → SettingSelect receives value="1" (override) instead of defaultValue="0"
 *
 * @example
 * // Dispatches SMS_SENDER ("text") → <SettingTextInput … />
 * // No override in values → falls back to defaultValue=""
 * <SettingRow
 *   type="text" id="SMS_SENDER" label="Sms Sender"
 *   defaultValue="" size={60} values={{}}
 * />
 */
export
function SettingRow({ type, values, ...props }) {
  if (props.fileOnly) {
    const override = values?.[props.id];
    const merged = override !== undefined ? { ...props, defaultValue: override } : props;
    return <SettingPhpDefine {...merged} />;
  }
  const Component = TYPE_MAP[type];
  if (!Component) {
    console.warn(`Unknown setting type: ${type}`);
    return null;
  }
  const override = values?.[props.id];
  const merged = override !== undefined ? { ...props, value: override } : props;
  return <Component {...merged} />;
}

/**
 * Renders a single setting.  Web-editable settings are wrapped in
 * <code>.jethro-config-form</code>; <code>fileOnly</code> (conf.php-only)
 * settings are wrapped in <code>.jethro-ini</code> instead.
 *
 * This is the simplest way to embed one setting in MDX documentation
 * without pulling in an entire group.
 *
 * @param {object}  props
 * @param {string}  props.id      — setting ID (e.g. <code>"SMS_5CENTSMS_APIKEY_ID"</code>)
 * @param {object}  [props.values] — optional runtime value overrides
 *
 * @example
 * // Web-editable text input inside .jethro-config-form:
 * <SingleSetting id="SMS_5CENTSMS_APIKEY_ID" />
 *
 * @example
 * // conf.php-only setting rendered as PHP define() inside .jethro-ini:
 * <SingleSetting id="BIBLE_API_URL" />
 *
 * @example
 * // With a saved value override:
 * <SingleSetting id="SMS_5CENTSMS_APIKEY_ID" values={{ SMS_5CENTSMS_APIKEY_ID: "abc123" }} />
 */
export function SingleSetting({ id, values }) {
  const s = SETTINGS[id];
  const cls = s?.fileOnly ? 'jethro-ini' : 'jethro-config-form';
  return (
    <div className={cls}>
      <SettingRow {...s} values={values} />
    </div>
  );
}
/**
 * Renders multiple arbitrary settings, splitting them into web-editable
 * settings (inside <code>.jethro-config-form</code>) and
 * <code>fileOnly</code> / conf.php-only settings (inside
 * <code>.jethro-ini</code>).
 *
 * Accepts either a list of setting IDs (<code>ids</code>) or a group /
 * category key (<code>group</code>) that resolves into setting IDs via
 * {@link resolveGroup}.  Categories are expanded recursively — all member
 * groups are collected.
 *
 * scope), <code>MultiSettings</code> accepts the setting IDs as a prop,
 *
 * @param {object}          props
 * @param {string|string[]} [props.ids]   — comma-separated string or array
 *                                          of setting IDs; ignored when
 *                                          <code>group</code> is set
 * @param {string}          [props.group]  — group or category key to resolve
 *                                          into setting IDs
 * @param {object}          [props.values] — optional runtime value overrides
 *
 * @example
 * // Comma-separated string — fileOnly settings render outside the yellow form:
 * <MultiSettings ids="BIBLE_API_APIKEY,BIBLE_API_URL,BIBLE_API_TIMEOUT" />
 *
 * @example
 * // Array of IDs:
 * <MultiSettings ids={["SMS_TESTMODE", "SMS_VERBOSE"]} />
 *
 * @example
 * // Group key — resolves group setting IDs via resolveGroup():
 * <MultiSettings group="messageBody" />
 *
 * @example
 * // Category key — recursively resolves all groups in CATEGORIES.sms:
 * <MultiSettings group="sms" />
 */
export function MultiSettings({ ids, group, values }) {
  const rawIds = group ? resolveGroup(data, group) : ids;
  const idList = Array.isArray(rawIds) ? rawIds : rawIds.split(',').map(s => s.trim()).filter(Boolean);
  const web = idList.filter(id => !SETTINGS[id]?.fileOnly);
  const ini = idList.filter(id => SETTINGS[id]?.fileOnly);
  return (
    <>
      {web.length > 0 && (
        <div className="jethro-config-form">
          {web.map(id => (
            <SettingRow key={id} {...SETTINGS[id]} values={values} />
          ))}
        </div>
      )}
      {ini.length > 0 && (
        <div className="jethro-ini">
          {ini.map(id => (
            <SettingRow key={id} {...SETTINGS[id]} values={values} />
          ))}
        </div>
      )}
    </>
  );
}


