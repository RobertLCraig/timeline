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
- [ ] WHEN a group owner or admin asks for an export, THE APP SHALL return a zip holding the group's events as JSON and every photo file those events use from `public/uploads/`. proves: `test_an_admin_can_export_a_group_as_a_zip`
- [ ] WHEN a plain member or an outsider asks for an export, THE APP SHALL refuse. proves: `test_a_member_cannot_export_a_group`
- [ ] WHEN an export is imported into a group the user admins, THE APP SHALL recreate its events, categories and photos there. proves: `test_an_export_imports_into_another_group`
- [ ] WHEN the same export is imported twice, THE APP SHALL update the events from the first import, not duplicate them. proves: `test_importing_twice_does_not_duplicate_events`
- [ ] WHEN an import zip holds a path outside its own folder, or a file that is not an image, THE APP SHALL reject that entry and write nothing outside `public/uploads/`. proves: `test_an_import_cannot_write_outside_uploads`
- [ ] WHEN a group is exported, THE ZIP SHALL contain no user ids, email addresses or tokens. proves: `test_an_export_carries_no_personal_account_data`
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
