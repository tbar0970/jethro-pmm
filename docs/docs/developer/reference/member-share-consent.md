---
title: Member directory share consent
sidebar_position: 12
---

# Member directory share consent (#792)

Consent to appear in the members-area directory is a **per person, per group**
row in `_person_share_consent`, not an attribute of the group's membership.

## Model

| Piece | Meaning |
|---|---|
| `_person_group.share_member_details` | "could share": gates whether members are offered the choice at all. Labelled "Members may share their contact info?" |
| `_person_share_consent(personid, groupid, data_permissions, inherit_mask, consented_on, granted_by)` | the person's grant; `data_permissions` is a bitmask of `DATA_PERM_VIEWNAME` (listed), `DATA_PERM_VIEWEMAIL` and `DATA_PERM_VIEWMOBILE` (contact details); `inherit_mask` bit 1 = Share my Name follows the person default, bit 2 = Share my Contact Info follows it; `consented_on`/`granted_by` record when consent was given and whether the member, staff, or the upgrade (carried over from the old group-wide setting) set it |
| `groupid = 0` rows | the person's **default consent**: applies to any could-share group where no explicit row exists. Explicit per-group rows always win; a column that inherits reads the default |
| mask 0 rows | an explicit **refusal** for the columns they cover |
| `DATA_PERM_VIEWNAME` (1) | the directory-listing bit; defined for consent only, never offered on the staff user-account form |

- The **Sharing** page in the members area and the staff person record's
  **Sharing** tab render the same table (`templates/share_consent.template.php`):
  one row per could-share group with two independent columns, **Share my Name**
  (the person is listed) and **Share my Contact Info** (email/phone shown with
  the name), plus a default row (labelled **Default**, with the longer
  explanation in its `title` tooltip) that applies to groups where no
  individual choice was made. Each column cell is one checkbox showing the effective
  value, followed by the mode: "set directly | use default" when the cell has
  an explicit choice, or "set directly | uses default" when it follows the
  default. The state text is plain; the other option is a grey, underlined,
  link that switches modes ("use default" inherits, "set
  directly" takes an explicit choice seeded from the default). Inherited cells
  are read-only and follow the default checkbox live (Datastar signals; see
  [Datastar](./datastar.md)). Mode text and links share one font size, and
  the two consent columns have a minimum width, so toggling cannot resize the
  table.
  A row whose columns both inherit is deleted, so ordinary saves never pin
  choices the person never made; rows whose submitted state is unchanged
  from the rendered state are left untouched. Each row shows when consent was
  recorded and flags staff-set and partly-inherited rows. Field-level consent
  is a future extension beyond the email/mobile bits; the mask column is
  already in place. Contact info implies the name: checking Contact info
  records the name grant too (including contact inherited from the default),
  and clearing Name clears contact.
- Consent rows are independent of membership: they survive leave/rejoin.
- Staff with `PERM_EDITGROUP` can set/clear consent on a group's member list
  ("Edit sharing settings"), e.g. recording verbal consent for offline members.
  That list exposes only the Visible (Name) checkbox; unticking it records an
  explicit refusal for both columns, while ticking it preserves existing
  contact consent. Contact consent itself is edited on the person's Sharing
  tab or by the member.
  Both write paths apply only rows the form actually rendered (a group joined
  between rendering and submission keeps its existing state), and the group
  page further intersects submitted person ids with the members the staff
  session can see through the `person` view, so a forged id cannot grant
  consent for someone the user could not otherwise reach.

## Visibility and field masking

The `member` view lists a person to a member-session viewer iff

- the viewer is a current member of a could-share, **non-archived** group, and
- the person is a current member of that group whose effective consent holds
  `DATA_PERM_VIEWNAME`: each column's explicit bits, or the person's default
  row (groupid 0) where that column inherits (`inherit_mask`), otherwise no
  consent

plus the standard archived-person/family guards. The viewer's own family is
always listed with full details. Where the viewer and the person share several
could-share groups, the view returns **one row per person**, unioning the
effective consent of those groups, so a contact grant in any of them is shown
once rather than producing duplicate directory entries. When the effective
consent lacks
`DATA_PERM_VIEWEMAIL` / `DATA_PERM_VIEWMOBILE`, the view returns empty strings
for `email` / `mobile_tel` / `work_tel`, so a name-only person is listed by
name with no contact details. The SQL asks `hasDataPerm(mask, symbol)` rather
than embedding numeric bits; PHP uses the matching `data_perm_bit()` and
`data_perm_mask_has()` helpers. The symbol map is shared with the staff
field-visibility feature (`dataPermBit`/`hasDataPerm` in SQL). Archived groups drop out of sharing entirely
(`NOT pg.is_archived`). A viewer's own consent never restricts what they can
see.

The members-area group page (`?view=_groups&groupid=N`) requires the viewer to
belong to a could-share group and lists only consented co-members (plus the
viewer themself). Before this change it listed every member of any shared
group, with no membership or consent check.

## Migration

`upgrades/2026-upgrade-to-2.40.sql` creates the table, adds `inherit_mask` via
an information-schema-guarded statement (MySQL has no `ADD COLUMN IF NOT
EXISTS`), and backfills consent **once** for every current member of a
share-enabled group, preserving the old implicit will-share behaviour; members
then opt out individually via the portal. Backfilled rows use the upgrade time
as `consented_on` and `granted_by = 'upgrade'`, shown as "migrated from the
old group setting", so they are not mistaken for staff-recorded consent. The one-time `SHARE_CONSENT_BACKFILLED`
hidden setting prevents a re-run of the upgrade from re-granting consent a
member has since withdrawn (a return to the default deletes the row, so
`INSERT IGNORE` alone is not enough).

## Where defined (keep in sync)

- `include/installer.class.php`, `upgrades/2026-upgrade-to-2.40.sql` — table + `member` view
- `db_objects/person_group.class.php` — consent API (`setShareConsent`, `applyShareConsentCheckboxes`, `applyGroupShareConsentCheckboxes`, `getDefaultShareConsent`, `getShareConsents`) and `getInitSQL`
- `members/views/view_5_sharing.class.php` — portal consent UI
- `templates/share_consent.template.php` — the sharing table shared by the portal page and the staff person tab (DataStar signals mirror defaults into read-only inherited columns)
- `views/view_5_groups.class.php` — staff consent UI
- `templates/view_person.template.php` — staff person record Sharing tab (editable for PERM_EDITGROUP; POST handled by `View_Persons::_processShareConsent`)
- `members/views/view_0_groups.class.php` — gated group page

## Known limitations

- Family address and `home_tel` remain governed by the congregation-wide
  `MEMBERS_SHARE_ADDRESS` setting; per-field consent is future work on the
  mask column.
- Site-defined membership statuses (e.g. "Former member") still count as
  membership; only person/group archiving or row deletion ends visibility.
- Re-adding a person who consented before leaving silently re-lists them once
  they rejoin (accepted fail-open tradeoff from the design review).
