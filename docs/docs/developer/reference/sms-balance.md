---
title: SMS balance and overdraft
sidebar_position: 25
---

# SMS balance and overdraft

What happens when a send needs more segments than the SMS balance depends on two things:

- **`SmsCapability::OVERDRAFT`** (provider): the provider's balance may go
  negative. An example is a host-kept credit ledger over a shared SMS account.
  No built-in provider declares it.
- **`SMS_OVERDRAFT_ALLOWED`** (conf.php only, default `false`): this
  installation is allowed to use that overdraft. It has `fileOnly: true` in
  `docs/jethrosettings.json` and no `setting` table row, so tenant admins
  can't change it. On shared hosting the operator sets it per tenant, e.g. on
  for paying tenants and off for tenants on free credits.

Only when both hold, `Jethro\Sms\overdraftAllowed()` returns true:

| | Overdraft allowed | Otherwise |
|---|---|---|
| Compose statusline | `⚠ Not enough SMS credit: N segments needed, M remaining.`, Send enabled | `⚠ Insufficient balance: N segments needed, M available.`, Send disabled |
| `?call=sms` | Sent | Refused with the same message, and nothing reaches the provider |

## Code (`include/jethro_sms.php`)

- `makeStatuslineConfig()` passes `balanceBlocksSend: !overdraftAllowed()` to
  `Sms\SmsStatuslineConfig`. `Sms\renderStatusline()` (jethro-sms
  `sms_statusline.php`) then blocks or warns. It checks only when it has
  preview rows and a known balance.
- `insufficientBalanceError()` is the server-side check. The statusline only
  disables the button, so this is what actually stops a send.
  `Call_SMS::_send()` runs it on the preview pass's deliveries, after the
  unicode policy check and before the real send. It counts segments as the
  statusline does:
  - Opted-out deliveries and the non-bearers of a shared-number message
    (`SmsDelivery::costBearer()` false, `DeduplicatingSmsProvider`) are
    skipped.
  - Each other message is counted as GSM-7 (160 characters, then 153 per
    segment) or UCS-2 (70, then 67), based on its own text.
- An unknown balance (`getSmsBalance()` null: no `GET_BALANCE`, or the lookup
  failed) is never enforced.
- The balance is cached per request and per session for 300 s
  (`SessionSmsCache`, cleared after a send). Two sessions sending at once can
  both pass against the same figure.

## Tests (`tests/functional/sms/`)

| Scenario | Provider | Balance | `SMS_OVERDRAFT_ALLOWED` | Asserts |
|---|---|---|---|---|
| `sms-insufficient-balance` (+`-v5`, `-mobilemessage`) | built-in | 0 | unset | blocked; direct `?call=sms` refused |
| `sms-low-balance` | Cellcast | 2 | true (no capability) | exact fit allowed; 4 needed blocked |
| `sms-overdraft` | overdraft | 0 | true | warns, Send enabled, sent |
| `sms-overdraft-not-allowed` | overdraft | 0 | false | blocked; direct `?call=sms` refused |

"Overdraft" in the Provider column is `FuncTest\OverdraftCellcastSmsProvider`
(`OverdraftCellcastSmsProvider.php`): Cellcast plus `OVERDRAFT`. The
`overdraft-provider.inc.php` file autoloads it and sets it as `SMS_PROVIDER`.
