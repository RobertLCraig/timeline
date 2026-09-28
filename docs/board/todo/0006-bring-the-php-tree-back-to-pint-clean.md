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
- [ ] WHEN `.\vendor\bin\pint.bat --test` is run on this repository, IT SHALL exit 0. proves: none -
      Pint is a style tool, not a test; its exit code is the check
- [ ] WHEN the suite is run after the reformat, IT SHALL pass with the same test and assertion count
      as before it. proves: none - the whole suite, not one test
<!-- AC:END -->

## Tasks
- [ ] Record the suite's test and assertion count before the change
- [ ] Run `.\vendor\bin\pint.bat` once and commit its output alone, so the diff is style only
- [ ] Run the suite and `pint --test` again

## Comments
