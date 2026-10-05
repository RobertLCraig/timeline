# Download a whole timeline and load it back

## Why
**There is no way to get a family's timeline out of the app.** The events and their photos live in
one SQLite file and one uploads folder on shared hosting. If that host loses them, or the family
wants to move, nothing they can download holds their history.

**What it costs.** Years of family photographs and dates depend on one server with no copy the
family owns. It is also the only way to move a timeline between installs.

**How it came to be this way.** Export was on the "future improvements" list from the start and
was never picked up.

## Links

**Relates to**
- `0004` - the decision that ranked export and import fourth of the five future improvements.
- `0009` - adds several photos per event. Whichever lands first, the export must carry every photo
  an event has.

## Not this card
- Scheduled or automatic backups, or backups of the whole server database.
- Exporting users, passwords, tokens, audit logs or moderation flags. Only a group's events, its
  own categories and its photos.
- Importing from other services (Google Photos, Facebook and the like).

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN a group owner or admin asks for an export, THE APP SHALL return a zip holding the group's events as JSON and every photo file those events use from `public/uploads/`. proves: `test_an_admin_can_export_a_group_as_a_zip`
- [x] WHEN a plain member or an outsider asks for an export, THE APP SHALL refuse. proves: `test_a_member_cannot_export_a_group`
- [x] WHEN an export is imported into a group the user admins, THE APP SHALL recreate its events, categories and photos there. proves: `test_an_export_imports_into_another_group`
- [x] WHEN the same export is imported twice, THE APP SHALL update the events from the first import, not duplicate them. proves: `test_importing_twice_does_not_duplicate_events`
- [x] WHEN an import zip holds a path outside its own folder, or a file that is not an image, THE APP SHALL reject that entry and write nothing outside `public/uploads/`. proves: `test_an_import_cannot_write_outside_uploads`
- [x] WHEN a group is exported, THE ZIP SHALL contain no user ids, email addresses or tokens. proves: `test_an_export_carries_no_personal_account_data`
<!-- AC:END -->

## Tasks
- [ ] Export route and download button in `GroupSettings.jsx`
- [ ] Import route and upload control in `GroupSettings.jsx`
- [ ] Tests named above
- [ ] `npm run build` and commit `public/build/`

## Plan
Stand in the timeline repository on the card's branch. Read `CLAUDE.md` and `HANDOVER.md` first.

1. **Format.** A zip with `timeline.json` (a format version number, the group's name, its own
   categories by name, and the events) and a `photos/` folder. Refer to photos by file name inside
   the zip, not by URL, so the zip survives a domain change.
2. **Idempotent import already exists.** Events carry `import_hash`, unique per group
   (`database/migrations/2026_06_06_000100_add_import_hash_to_events_table.php`), and
   `EventController` and `PostTimelineEventTool` already update instead of duplicating on a
   repeated hash. Write each exported event's hash into the JSON and import through
   `App\Support\EventCreator`, so the same rules apply as for every other write.
3. **Unsafe zips.** Never trust a name from inside the zip as a path. Take only the base name,
   check the content is an image, and write a fresh file name under `public/uploads/`.
4. **Shared hosting limits.** Build the zip with PHP's `ZipArchive` into a temp file and stream it.
   Find out whether `ext-zip` is enabled on production by reading `deploy.sh`, `composer.json` and
   `HANDOVER.md`; if nothing says, write that on this card as an open point for the deploy, and
   keep building.
5. If card `0009` (several photos per event) has landed, export and import the full photo list;
   if not, `image_url` alone. Check `docs/board/done/` to find out.
6. Run `composer test`; it must be green.

## Comments

**2026-10-05** RESULT: done
TESTS: +6 new, all green (59 passed in the full suite)
TOUCHED: app/Http/Controllers/TimelineExportController.php
TOUCHED: routes/api.php
TOUCHED: resources/js/pages/GroupSettings.jsx
TOUCHED: tests/Feature/TimelineExportTest.php
TOUCHED: public/build/ (manifest.json, main-*.js, mobilenet_v2-*.js rebuilt)
OUT-OF-SCOPE: none

Built: GET /api/groups/{slug}/export and POST /api/groups/{slug}/import, both behind group.role:owner,admin (import also behind ability:events:write and the events-write throttle, like every other event write). The zip holds timeline.json (version 1, group name and description, the group's own categories by name/icon/colour, the events) and photos/. Photos are named by zip path; an http(s) photo link that is not in uploads/ stays a link. Import goes through EventCreator::importUpsert. Only entries that sit directly in photos/, are 5 MB or less and whose content getimagesizefromstring reads as jpeg/png/gif/webp are written, under sha1-of-content names in public/uploads/; everything else is listed in the response's `rejected`. ZipArchive::extractTo is never used. A Download button and a Load control are in a new 'Download & Restore' section of GroupSettings.jsx.

Each test was first watched red against a naive draft: the member got 200, the second import made 4 events, the unsafe zip wrote through the zip's own path, and the export carried created_by/user ids. The tests use a temporary public path, so they never touch the real uploads folder.

Assumptions, for the reviewer:
- The export stamps an import_hash on each event that has none (sha1 of group id, event id, created_at). Without that, importing the zip back into its own group duplicated every event. So the export writes to the events table.
- Another member's private events (visibility or social_visibility 'private') are left out of an admin's export. The card did not say; I kept them private.
- Imported events are created_by the importing user and their category resolves by name to a global or the group's own category; an unknown name becomes no category.
- 0009 (several photos) is in ai-review, not done/, but its image_urls code is on this branch, so the export carries the full photo list (plan step 5).
- Re-importing reuses the same photo files (content-hash names) instead of writing copies.

Open point for the deploy: nothing in deploy.sh, composer.json or HANDOVER.md says whether ext-zip is on for production PHP. It is on in local Herd. Check hPanel -> PHP Extensions before using this on the live site.

Not checked: the browser. Herd serves C:\Dev\timeline, not this worktree. The Download link is a plain GET that relies on the session cookie and the Referer being sent; that still needs a look in Edge. This worktree's node_modules was stale (nsfwjs/core did not resolve); npm ci here fixed it, so the mobilenet chunk hash changed in public/build too. pest.bat is not in vendor/bin here; the suite ran with php artisan test (PHPUnit), and pint.bat passed.
