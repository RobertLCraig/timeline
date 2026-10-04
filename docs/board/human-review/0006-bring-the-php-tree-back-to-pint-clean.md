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

### 2026-10-04 review (v20261004212824-50c7)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 25s, run by this job rather than reported by the card.

**acceptance: defect**

I ran the check myself. It fails.

**What I did:** I ran `.\vendor\bin\pint.bat --test` in `C:\Dev\timeline`.

**Result:** It ends with exit code 1, not 0. Pint still flags 5 files. None of them are in commit `22c2bde`:

- `app/Models/GroupMember.php`: line endings.
- `config/cors.php`: line endings.
- `database/migrations/2026_02_25_000200_create_audit_logs_table.php`: line endings, class definition and brace position.
- `database/migrations/2026_02_25_000300_add_mfa_to_users_table.php`: same three faults.
- `database/migrations/2026_02_25_000400_add_google_id_to_users_table.php`: same three faults.

The anonymous `Migration` class in `create_audit_logs_table.php` already has its brace on the next line. So the faults probably come from Windows line endings (CRLF, the two-character line break). The other two fixers may only trigger because of those line endings. That does not change the result. The card asks for a clean `pint --test` on this repository, and the check is red.

The builder said "same 30 files the card counted". The card's count was taken in a different worktree, so it missed these 5 files. They need one more Pint pass, committed alone, and then `pint --test` again.

**Criterion #2:** The suite is green. The script ran it before this review: `phpunit` exited 0. I have no proof against the "37 tests, 92 assertions" count, so I leave #2 as it is.

UNMET: #1 `pint --test` still exits 1 and flags GroupMember.php, config/cors.php and three 2026_02_25 migrations (line_ending, class_definition, braces_position) that the reformat commit never touched.

VERDICT: defect

**scope: sound**

I could not break this card.

- **The commit stays inside the fence.** Commit `22c2bde` touches only the 30 PHP files the card named. The other files in the diff (`resources/js/lib/nsfwScan.js`, `EventForm.jsx`, the `public/build` assets, the board cards) come from other commits in the range, not from this one.
- **Only style changed.** I compared the commit with whitespace ignored. What is left is import order, `new Google2FA()` → `new Google2FA` (3 times), `new SvgImageBackEnd()` → `new SvgImageBackEnd`, and two `DemoSeeder::run` titles that moved from double to single quotes. The titles have no `$` and no escapes, so the strings are the same. No behaviour changed.
- **Pint looked red here, but only because of line endings.** On this working copy, `pint --test` fails on 5 files. Examples are `app/Models/GroupMember.php` and `database/migrations/2026_02_25_000200_create_audit_logs_table.php`. `git ls-files --eol` shows that the repository stores them with LF line endings (`i/lf`). This checkout has them with Windows CRLF endings (`w/crlf`). In the committed file, the migration's class brace is already on its own line, which is the Pint style. A fresh checkout passes, so criterion #1 holds for the repository.
- **The suite is green.** The script's own run of `phpunit` exited 0. That agrees with the builder's 37 tests and 92 assertions.

Nothing is left half done.

VERDICT: sound

**breakage: sound**

I tried to find something that this change breaks. I found nothing.

- **Scope:** Commit `22c2bde` changes only the 30 PHP files. Git shows `30 files changed`. The other files in the full diff (board cards, JS, build assets) come from other commits in the range, not from this card.
- **Code changes:** I compared the code and ignored whitespace. Only three kinds of change remain:
  1. The `use` lines are in a new order. PHP does not care about this order.
  2. `new Google2FA()` became `new Google2FA`, in `AuthController` `mfaEnable`, `mfaConfirm` and `mfaVerify`. `new SvgImageBackEnd()` became `new SvgImageBackEnd`, in `mfaEnable`. With no arguments, PHP builds the same object either way.
  3. `return new class extends Migration {` now has its `{` on the next line, in the migrations. This is only where the brace sits.
- **The two `DemoSeeder` titles:** They went from double quotes to single quotes. Neither title has a `$` or a backslash, so each string is the same.
- **Comments and docblocks:** None changed, so no comment became false.
- **Callers:** No names, method signatures or array keys changed, so no caller needs an update.
- **Tests:** The suite was already green before I started (`phpunit` exited 0).

VERDICT: sound

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: `pint --test` still exits 1 and flags GroupMember.php, config/cors.php and three 2026_02_25 migrations (line_ending, class_definition, braces_position) that the reformat commit never touched.


**2026-10-05** The reviewer's acceptance lens returned this card defect: I ran the check myself. It fails. The reviewer's scope lens returned this card sound: I could not break this card. The reviewer's breakage lens returned this card sound: I tried to find something that this change breaks. I found nothing. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 2 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer reopens every criterion it reports unmet, and the reviews that sent this card back named no criterion they disproved, so it came back with 2 of 2 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Add or reopen the criterion the finding breaks and move it back to todo/, or say here why the finding is wrong.
