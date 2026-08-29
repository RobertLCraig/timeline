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
- [ ] #1 THE HANDOVER SHALL describe the server scan, the settings table, the flag table and the
      admin review queue as shipped, naming the files that carry them.
- [ ] #2 THE HANDOVER SHALL NOT list any moderation item as planned except the client-side pre-scan,
      which SHALL point at card 0001 rather than restating its design.
- [ ] #3 THE "Future Improvements" list SHALL be replaced by a pointer to the board.
<!-- AC:END -->

## Tasks
- [ ] Rewrite the Phase 4 section as a description of what exists
- [ ] Point the remaining gap at card 0001
- [ ] Replace "Future Improvements" with a board pointer
