# Bring the PHP tree back to Pint-clean

## Why
**`.\vendor\bin\pint.bat --test` fails on 30 files nobody is working on.** The drift is in `app/`,
`config/`, `database/migrations/` and `database/seeders/` - spacing, import order, brace position -
and none of it was introduced by a card. Run on 2026-09-28 from the `0005` worktree, it listed 30
files, the same count `0002` recorded on 2026-08-29.

**What it costs.** Every build session is told to run Pint for style, and a bare run is red before
anything is changed. So each session falls back to `pint --dirty`, and a session that edits one line
of a drifted file gets the whole file re-spaced: `0001`'s builder threw away exactly that rewrite of
`UploadController::store()` to stay inside its fence. The style check cannot tell a new fault from
old ones.

**How it came to be this way.** The code predates the Pint check on this board, and every card since
has been fenced away from files it did not need, correctly, so nobody's scope ever covered it.

## Links

**Relates to**
- `0001` - its builder declined Pint's rewrite of `UploadController.php` because that file's
  `store()` is fenced off from it; this card is the place that rewrite belongs.

## Not this card
Any change in behaviour. This is whitespace, import order and brace position only, as Pint writes
it; if Pint's output changes what code does, stop and say so.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN `.\vendor\bin\pint.bat --test` is run on this repository, IT SHALL exit 0. proves: none -
      Pint is a style tool, not a test; its exit code is the check
- [x] WHEN the suite is run after the reformat, IT SHALL pass with the same test and assertion count
      as before it. proves: none - the whole suite, not one test
<!-- AC:END -->

## Tasks
- [ ] Record the suite's test and assertion count before the change
- [ ] Run `.\vendor\bin\pint.bat` once and commit its output alone, so the diff is style only
- [ ] Run the suite and `pint --test` again

## Comments

**2026-10-04** RESULT: done
TESTS: +0 new, all green (37 tests, 92 assertions before and after)
TOUCHED: app/Http/Controllers/AdminController.php, app/Http/Controllers/AuthController.php, app/Http/Controllers/GroupController.php, app/Http/Controllers/UploadController.php, app/Http/Controllers/VisibilityController.php, app/Http/Middleware/EnsureGroupRole.php, app/Http/Middleware/EnsureSuperAdmin.php, app/Http/Middleware/ResolveGroup.php, app/Models/AppSetting.php, app/Models/AuditLog.php, app/Models/Event.php, app/Models/Group.php, app/Models/GroupInvite.php, app/Models/ReferralCode.php, app/Models/UploadFlag.php, app/Models/User.php, config/services.php, database/migrations/2024_01_01_000010_create_groups_table.php, database/migrations/2024_01_01_000020_create_group_members_table.php, database/migrations/2024_01_01_000030_create_event_categories_table.php, database/migrations/2024_01_01_000040_create_events_table.php, database/migrations/2024_01_01_000050_create_referral_codes_table.php, database/migrations/2024_01_01_000060_create_group_invites_table.php, database/migrations/2026_02_25_000500_make_events_created_by_nullable.php, database/migrations/2026_02_25_000600_create_consent_logs_table.php, database/migrations/2026_02_25_000700_create_app_settings_table.php, database/migrations/2026_02_25_000800_create_upload_flags_table.php, database/migrations/2026_06_06_000100_add_import_hash_to_events_table.php, database/seeders/DatabaseSeeder.php, database/seeders/DemoSeeder.php
OUT-OF-SCOPE: none

One bare `.\vendor\bin\pint.bat` run, committed alone (22c2bde). Same 30 files the card counted. `pint --test` now exits 0. Suite: 37 tests / 92 assertions before and after.

Checked the diff with whitespace ignored. The only non-whitespace changes: import lines reordered, `new X()` became `new X` (3x Google2FA, 1x SvgImageBackEnd), and two DemoSeeder titles moved from double to single quotes ("Robert Turns 50", "Grandma Eleanor Turns 80" - no `$` or escapes, so the same string). No behaviour change.

Note: the session prompt names `.\vendor\bin\pest.bat`, but this repo has no Pest installed; the suite runs as `.\vendor\bin\phpunit.bat` (what `composer test` wraps). No browser check needed: no frontend change. HANDOVER.md is at the repo root, not docs/; it needed no update for a style-only card.
