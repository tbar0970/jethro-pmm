---
title: Object edit locks
---

# Object edit locks

`DB_Object` (`include/db_object.class.php`) provides advisory edit locks so two users cannot
edit the same record at once. Locks are rows in `db_object_lock`
(`objectid`, `object_type`, `lock_type`, `userid`, `expires`).

| Method | Behaviour |
|---|---|
| `acquireLock($type)` | Inserts a lock row expiring `LOCK_LENGTH` from now, unless another user holds an unexpired lock. On ~1% of calls made outside a transaction, also purges every expired lock. |
| `haveLock($type)` / `canAcquireLock($type)` / `getLockHolder($type)` | Read only unexpired rows (`expires > NOW()`). |
| `releaseLock($type)` | Deletes the current user's lock on this object. |
| `DB_Object::releaseAllLocks($userid)` | Deletes all of `$userid`'s locks. Called from `User_System::_logOut()` (explicit logout and session timeouts). |

`DB_Object::save()` acquires the lock itself when the caller does not already hold it.

## Expired-lock purge

Expired rows are ignored by every read, so purging them is housekeeping only.

`acquireLock()` skips the purge when `$db->inTransaction()`. Callers such as the CSV importer
(`View_Admin__Import`) acquire locks inside one long transaction, and on MariaDB ≥ 11.6.2
(`innodb_snapshot_isolation=ON` by default) a table-wide `DELETE ... WHERE expires < NOW()`
there fails with error 1020 "Record has changed since last read" whenever another session
touched `db_object_lock` after the transaction's snapshot. Interactive edits, which acquire
locks outside a transaction, keep the table tidy.

The purge is deliberately not done at logout: `db_object_lock` has no index on `expires`, so a
table-wide DELETE there would scan and wait on other sessions' uncommitted lock rows (for
example a long import), stalling an unrelated logout.
