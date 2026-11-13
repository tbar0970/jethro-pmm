{/*
Prompt to regenerate this file when provider capabilities change:

For each concrete SmsProvider implementation — MobileMessageSmsProvider,
FiveCentSmsV5Provider, CellcastSmsProvider, and FiveCentSmsV4Provider
(deprecated) — read its hasCapability(SmsCapability) support list.
Omit TemplateSmsProvider (the generic catch-all) and decorators from
the table. Map each SmsCapability enum case to Title Case (e.g.
GET_BALANCE → "Get Balance") and pair it with its human-readable
description from the enum's docblock or a short summary of the
interface method it gates. Produce a single HTML table with one
row per capability: bold Title Case name on the first line,
description on the second line (using <br />). Use ✅ for supported
and — for unsupported. Columns: Capability, 5Cent v5, Cellcast,
MobileMessage, 5Cent v4.
*/}

### SMS Provider Capability Matrix

<table>
  <thead>
    <tr>
      <th>Capability</th>
      <th>5Cent v5</th>
      <th>Cellcast</th>
      <th>MobileMessage</th>
      <th>5Cent v4</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Get Balance</strong><br />Query the account balance (remaining SMS credits).</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅</td>
</tr>
    <tr>
      <td><strong>Get Sender IDs</strong><br />List registered alphanumeric sender IDs.</td>
      <td>✅</td>
      <td>✅<br />(business names)</td>
      <td>✅<br />(no ACMA flag exposed)</td>
      <td>—</td>
</tr>
    <tr>
      <td><strong>Deferred Send</strong><br />Schedule a message for future delivery via a <code>sendAt</code> timestamp.</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅<br />(schedule &gt;60 s ahead)</td>
      <td>—</td>
</tr>
    <tr>
      <td><strong>Deferred Send Cancel</strong><br />Cancel a previously scheduled message before delivery.</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅</td>
      <td>—</td>
</tr>
    <tr>
      <td><strong>Register Sender Number</strong><br />Register a phone number as a sender. Verification may be via OTP or out-of-band link.</td>
      <td>✅</td>
      <td>✅<br />(OTP)</td>
      <td>✅<br />(verification link)</td>
      <td>—</td>
</tr>
    <tr>
      <td><strong>Register Sender ID</strong><br />Register a sender ID (business identity) with the upstream gateway.</td>
      <td>✅</td>
      <td>✅</td>
      <td>—<br />(dashboard / ACMA)</td>
      <td>—</td>
    </tr>
    <tr>
      <td><strong>List Opt-Outs</strong><br />List phone numbers that have opted out of messages.</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅</td>
      <td>—</td>
    </tr>
    <tr>
      <td><strong>Remove Opt-Out</strong><br />Remove a number from the upstream opt-out list.</td>
      <td>✅</td>
      <td>—</td>
      <td>✅</td>
      <td>—</td>
    </tr>
    <tr>
      <td><strong>Batch Delivery Query</strong><br />One query returning recent delivery statuses for all messages.</td>
      <td>✅</td>
      <td>✅</td>
      <td>✅</td>
      <td>—</td>
    </tr>
    <tr>
      <td><strong>Import History</strong><br />Import / sync SMS history.</td>
      <td>✅ partial<br/> - senders not imported</td>
      <td>✅</td>
      <td>✅</td>
      <td>—</td>
    </tr>
  </tbody>
</table>

**How far ahead a send can be scheduled:** 365 days (5CentSMS v5), 24 hours (Cellcast), no limit (MobileMessage, which
enforces only a 60-second minimum), and none (5CentSMS v4, template gateways). Jethro applies the same limits to the
compose dialog's schedule field and validates them server-side; see [Scheduling](../user-manual/sms.mdx#schedule-send).
