---
title: DB session identity (staff vs member)
sidebar_position: 10
---

# DB session identity: `@current_user_id` vs `@current_member_id`

Row-level visibility is enforced by DB views keyed off two MySQL session
variables, each read through a stored function:

| Variable | Function | Set by | Meaning |
|---|---|---|---|
| `@current_user_id` | `getCurrentUserID()` | `User_System` (control centre) via `JethroDB::setCurrentUserID()` | Staff account id; `-1` = public/CLI (unrestricted) |
| `@current_member_id` | `getCurrentMemberID()` | `Member_User_System` (members area) via `JethroDB::setCurrentMemberID()` | Logged-in member's person id |

A request sets exactly one of them. Both NULL → the views return nothing.

| View | Staff session | Member session |
|---|---|---|
| `person` | Account's congregation/group restrictions | All rows (staff restrictions never apply) |
| `person_group` | Own + shared groups, limited by `account_group_restriction` | Shared groups (`owner IS NULL`) |
| `member` | Nothing | Own family + co-members of `share_member_details` groups |
| `abstract_note` | Own pending assignments, or all notes with `PERM_VIEWNOTE` | Nothing |

## Why

The members area used to set `@current_user_id` to the member's person id. A
member who also had a *restricted staff account* then had those staff
restrictions applied in the members area, hiding their own family members
(upstream #1173 edit-family crash, #1433 planned-absence crash) and roster
assignees. Ordinary members (no restriction rows) were already unrestricted in
`person`, so the member-session clause gives everyone that same behaviour.
Members-area scoping is done by the `member` view and PHP family checks
(e.g. `members/views/view_0_add_planned_absence.class.php`).

## Where defined (keep in sync)

- `include/installer.class.php` — both functions, `member` view
- `db_objects/person.class.php`, `db_objects/person_group.class.php` — `getViewSQL()`
- `upgrades/2026-upgrade-to-2.40.sql` — function + view recreation for existing DBs

## Querying by hand

```sql
SET @current_user_id = 1;                               -- as staff account 1
SET @current_user_id = NULL, @current_member_id = 42;   -- as member 42
```
