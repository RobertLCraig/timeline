# Reconcile the handover's Phase 4 section with what shipped

## Why
**The handover says content moderation has not been built, and most of it has.** `HANDOVER.md`
carries a section headed "Planned: Content Moderation (Phase 4)" listing an architecture decision,
admin settings, a verification queue and a client pre-scan as future work. Four of those five have
been in the repository since February: `UploadController` calls Sightengine, `app_settings` and
`upload_flags` exist as tables, and `AdminController::uploadFlags` and `reviewFlag` are routed and
rendered in `AdminPanel.jsx`.

**What it costs.** Someone picking this project up plans work that is already done. They also miss
the one part that genuinely is missing, because it is the fourth bullet of five under a heading that
says none of it exists.

**How it came to be this way.** The section was written before the work and nobody came back to it
when the work landed.

## Links

**Relates to**
- `0001` - builds the one moderation part that really is still open, and it is the item this
  section has to be left pointing at rather than restating.

## Not this card
Building the pre-scan. That is card `0001`, linked above.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 THE HANDOVER SHALL describe the server scan, the settings table, the flag table and the
      admin review queue as shipped, naming the files that carry them.
- [x] #2 THE HANDOVER SHALL NOT list any moderation item as planned except the client-side pre-scan,
      which SHALL point at card 0001 rather than restating its design.
- [x] #3 THE "Future Improvements" list SHALL be replaced by a pointer to the board.
<!-- AC:END -->

## Tasks
- [x] Rewrite the Phase 4 section as a description of what exists
- [x] Point the remaining gap at card 0001
- [x] Replace "Future Improvements" with a board pointer

## Comments

**2026-08-29** Rewrote `HANDOVER.md` §"Planned: Content Moderation (Phase 4)" as §"Content
Moderation", describing what is in the repository rather than what was intended. Every claim was
read out of the code first, and four of them contradicted the old text:

- **Quarantine does nothing to the image.** `AdminController::reviewFlag` sets `status`,
  `reviewed_by` and `reviewed_at` and writes an audit log. It does not null the event's `image_url`,
  does not delete the file and does not move it to `storage/quarantine/`, all three of which the old
  section promised. The image stays live at its `public/uploads/` URL after quarantine. This is now
  a blockquote in the section because it is the one line a reader is most likely to trust wrongly.
  It is also card `0003`'s criterion #3, which will therefore fail as written — not mine to fix.
- **The tab names were wrong.** There is no "Flagged Uploads" tab; `AdminPanel.jsx` renders
  **🚩 Content Flags** and **⚙️ NSFW Settings**.
- **`app_settings` is not cached.** `AppSetting::get()` is a `find()` per call. The old text said
  cached.
- **Two of the four documented env vars are dead.** Only `SIGHTENGINE_API_USER` and
  `SIGHTENGINE_API_SECRET` are read (via `config/services.php`). `SIGHTENGINE_NUDITY_THRESHOLD` and
  `NSFW_CHECKS_ENABLED` appear in `.env.example` and nothing reads them — the real controls are the
  `app_settings` rows, seeded `0` and `0.6`, not `true` and `0.7`. **`.env.example` still carries the
  two dead lines.** Correcting it is a code change and this card is the handover, so I left it and
  am recording it here instead.

Also named: the scan is inline in `UploadController::store`, not a queued job — there is no
`ScanUploadForContent` class anywhere in the repository, though the old section referred to one.

Criterion #2. The pre-scan is the only moderation item left open and it points at card `0001`,
naming its two files and no more. One judgement call: the section also says, in one sentence, that
none of this has ever run against a real Sightengine account and that the check is a card. That is a
statement about the shipped code's evidence rather than a planned feature, so I read it as inside
#2 — but it is the one place a reviewer could disagree, so it is flagged here.

Criterion #3 was already true before I started: commit `71c643a` replaced the "Future Improvements"
list with the "Work in flight" pointer to `docs/board/`. I ticked it on the state of the document,
not on work I did. What I did do there was delete its last paragraph, which said this very section
was stale and card 0002 owed the rewrite, and replace it with the two open cards.

Checks: `.\vendor\bin\phpunit.bat` — 35 tests, 88 assertions, green. (`vendor/bin/pest.bat` does not
exist in this project; PHPUnit is the runner.) `.\vendor\bin\pint.bat --dirty --test` passes. A bare
`.\vendor\bin\pint.bat --test` fails on 30 files, all pre-existing drift in `app/`, `config/`,
`database/` — this card touched one markdown file and no PHP. No browser check is needed or possible
from this worktree; the diff is documentation only.

### 2026-08-29 review (v20260829170604-da3b)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 20s, run by this job rather than reported by the card.

**acceptance: sound**

**#1 ÔÇö traced.** `HANDOVER.md` ┬º"Content Moderation" describes four shipped parts and names each file. Every claim holds:

- Scan: `UploadController::store`, with helpers `scanEnabled`, `callSightengine`, `topScore` ÔÇö inline, no job class (`ScanUploadForContent` exists nowhere in `app/`), `models=nudity-2.0`, catch-and-log, response `{url, filename, flagged, flag_id}`.
- Settings: `2026_02_25_000700_create_app_settings_table` seeds `0` and `0.6`; `AppSetting::get` is a bare `find()`, so "no cache" is right.
- Flags: `2026_02_25_000800_create_upload_flags_table` ÔÇö columns match, `created_at` only, `scores` cast to array in `App\Models\UploadFlag`.
- Queue: `AdminController::uploadFlags` (paginate 20) and `reviewFlag`, routed in `routes/api.php`; UI is `ContentFlagsTab` / `NsfwSettingsTab` in `AdminPanel.jsx`, labelled ­ƒÜ® Content Flags and ÔÜÖ´©Å NSFW Settings.

The blockquote is correct: `reviewFlag` writes only `status`, `reviewed_by`, `reviewed_at` plus an audit row. `config/services.php` reads two env vars; `.env.example` carries two dead ones, as stated.

**#2 ÔÇö traced.** Only "Not settled: the client-side pre-scan" remains open; it names `resources/js/lib/nsfwScan.js` and `EventForm.jsx` (both exist) and defers to card `0001`, which is in `docs/board/human-review/`. No design restated. The Sightengine-evidence sentence points at a card, not a plan.

**#3 ÔÇö traced.** No "Future Improvements" heading survives; ┬º"Work in flight" points at `docs/board/todo/` and `docs/board/human-review/`, both present.

VERDICT: sound

**scope: sound**

Scope check.

**Touched:** `HANDOVER.md` and its own card. No code. The fence held ÔÇö nothing was built for card 0001. `resources/js/lib/nsfwScan.js` and `EventForm.jsx` are named only, not changed.

**Two things I tried to call creep and could not:**

1. The card-`0003` sentence in `HANDOVER.md` ┬º"Work in flight". AC#2 allows one open moderation item; this names a second. But card 0003 already sat on the board before this commit, and the sentence records evidence about shipped code (`UploadController::scanEnabled` and `callSightengine` have never run against a live account), not a feature to build. It cannot make a reader plan done work, which is the harm AC#2 guards.

2. `.env.example` left carrying two dead lines. That is a code change, correctly refused, and it is not lost ÔÇö ┬º"Environment" says nothing reads them.

**Half done:** none found. ┬º"Content Moderation" covers all four shipped parts, and no "planned" moderation text survives in `HANDOVER.md`, `README.md` or `RUNBOOK.md`.

AC#3 was true from commit `71c643a`; the card comment says so instead of claiming it.

VERDICT: sound

**breakage: defect**

**Finding ÔÇö the evidence sentence is wrong.**

`HANDOVER.md`, section "Admin review queue", last paragraph says the scan, the threshold and the failure path are "as-written rather than as-observed".

Two of those three are observed. `tests/Feature/UploadScanTest.php` fakes Sightengine and asserts both: `test_an_allowed_upload_is_still_scanned_server_side` proves the call goes out, `test_an_image_over_the_threshold_is_flagged_for_review` proves the 0.6 threshold writes an `upload_flags` row with `top_score` 0.91. Only the failure path in `UploadController::store`'s `catch` is untested.

The section names a file for every other shipped part but never names that test file. A reader takes the sentence at face value, believes the scan is unproved, and writes tests that already exist ÔÇö or distrusts working code.

Everything else checked out. I read `UploadController::store`, `scanEnabled`, `topScore`, `AppSetting::get`, both migrations, `AdminController::getSettings`/`updateSettings`/`uploadFlags`/`reviewFlag`, `routes/api.php` and `EventForm.jsx`. Every claim matched.

The two contradictions left behind ÔÇö `.env.example`'s dead `NSFW_CHECKS_ENABLED` line, and card 0003 criterion #3 asking a person to verify a quarantine that `AdminController::reviewFlag` never performs ÔÇö are both written down in the card comment, so they are not silent.

VERDICT: defect


**2026-08-29** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 3 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer never unticks a criterion - it is forbidden from editing acceptance at all - so the card came back with 3 of 3 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Untick what the reviewer disproved and move it back to todo/, or say here why the finding is wrong.
