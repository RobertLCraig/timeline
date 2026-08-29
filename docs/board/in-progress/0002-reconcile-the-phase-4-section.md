# Reconcile the handover's Phase 4 section with what shipped

## Why
`HANDOVER.md` carries a section headed "Planned: Content Moderation (Phase 4)" describing an
architecture decision, admin settings, a verification queue and a client pre-scan as future work.
Four of those five are in the repository and have been since February: `UploadController` calls
Sightengine, `app_settings` and `upload_flags` exist as tables, and `AdminController::uploadFlags`
and `reviewFlag` are routed and rendered in `AdminPanel.jsx`.

Someone picking this up would plan work that is already done, and would not find the one part that
genuinely is missing, because it is the fourth bullet of five in a section labelled "planned".

## Not this card
Building the pre-scan. That is card 0001.

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
