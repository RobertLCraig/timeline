# Several photos on one event

## Why
**An event can hold one photo and no more.** A wedding, a holiday or a birthday has dozens of
pictures, and the timeline can show one of them. The rest live somewhere else, behind the event's
"album" link, if anybody pasted one.

**What it costs.** The family's best pictures of an event are mostly not on the timeline. The photo
mosaic view and the event pop-up can only ever show the single picture, so the timeline reads as
thinner than the family's real record of the day.

**How it came to be this way.** The `events` table was built with one `image_url` column and an
`album_url` link for "the rest", and nobody went back to it.

## Links

**Relates to**
- `0004` - the decision that ranked multi-image galleries first of the five future improvements.
- `0003` - verifies the server photo scan against a real Sightengine account. This card must not
  change the upload path underneath that check, which is why `## Not this card` fences it off.

## Not this card
- Any change to `POST /api/upload` or `UploadController`. Each gallery photo is uploaded through
  the existing endpoint, one call per photo, so the scan and the flag queue are untouched.
- The client-side pre-scan in `resources/js/lib/nsfwScan.js`. It already runs per file.
- Removing `album_url`. It stays, for albums that live elsewhere.
- Captions, reordering by drag, or per-photo visibility.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN an event is saved with several photo URLs, THE APP SHALL store them in the order given and return them with the event. proves: `test_an_event_stores_several_photos_in_order`
- [x] #2 WHEN an event has photos, THE APP SHALL keep `image_url` equal to the first one, so every existing view and API client still sees a cover photo. proves: `test_the_first_photo_is_the_cover_image`
- [x] #3 WHEN an event is saved with more photos than the limit, THE APP SHALL refuse it with a validation error. proves: `test_an_event_with_too_many_photos_is_rejected`
- [x] #4 WHEN a user who may not edit an event tries to change its photos, through REST or MCP, THE APP SHALL refuse. proves: `test_a_non_owner_cannot_change_event_photos`
- [x] #5 WHEN an existing event with only `image_url` is read, THE APP SHALL return it as a one-photo gallery. proves: `test_a_legacy_event_reads_as_a_one_photo_gallery`
- [ ] #6 WHEN the event pop-up opens on an event with several photos, THE APP SHALL let the user step through them. proves: manual - a browser check of `EventModal` on `timeline.test`
- [ ] #7 WHEN an MCP import is run again with the same `import_hash` and no photo fields, THE APP SHALL keep the stored gallery and cover unchanged. proves: `test_a_reimport_without_photo_fields_keeps_the_gallery`
<!-- AC:END -->

## Tasks
- [ ] Additive migration for the photo list
- [ ] Accept and return the list in `App\Support\EventCreator`, the REST event endpoints and the MCP post/update/get tools
- [ ] Multi-file picker in `EventForm.jsx`, one upload call per file
- [ ] Step-through in `EventModal.jsx`; all photos in `PhotoMosaicView.jsx`
- [ ] Tests named above
- [ ] `npm run build` and commit `public/build/`

## Plan
Stand in the timeline repository on the card's branch. Read `CLAUDE.md` (stack, "Agent &
Programmatic Access", the prod data warning) and `HANDOVER.md` section "Content Moderation" first.

1. **Storage.** A new `event_images` table (`event_id`, `url`, `position`, timestamps) is the
   usual shape; a JSON column on `events` is the lazy alternative. Pick one and say which on the
   card. The migration must be additive: production holds real data, so no drop, no rewrite of
   `image_url`. Legacy rows keep working by reading `image_url` as a one-item list.
2. **One code path.** All writes go through `App\Support\EventCreator`
   (`app/Support/EventCreator.php`), which both REST (`EventController`) and MCP
   (`app/Mcp/Tools/PostTimelineEventTool.php`, `UpdateTimelineEventTool.php`,
   `GetTimelineEventTool.php`) call. Add the photo list there and set `image_url` to the first
   entry. Authorisation is already enforced on those paths; add the test to
   `tests/Feature/McpAuthorizationTest.php` as the house rule requires.
3. **The limit.** Put it in one named constant. 20 per event is a reasonable start; it is a soft
   product limit, not a security control, so it does not need a person to set it.
4. **Front end.** `EventForm.jsx` already uploads one file through the pre-scan then
   `POST /api/upload`. Loop that per file. Styles go in `views.css` / the page CSS with `var(--...)`
   tokens.
5. Run `composer test` and `npm run test:js`; both must be green.

## Comments

**2026-10-05** RESULT: partial
TESTS: +5 new, all green (42 PHPUnit, 9 node --test)
TOUCHED: database/migrations/2026_10_05_000100_add_image_urls_to_events_table.php, app/Models/Event.php, app/Support/EventCreator.php, app/Http/Controllers/EventController.php, app/Mcp/Tools/PostTimelineEventTool.php, app/Mcp/Tools/UpdateTimelineEventTool.php, app/Mcp/Tools/GetTimelineEventTool.php, app/Mcp/Servers/TimelineServer.php, resources/js/pages/EventForm.jsx, resources/js/components/views/EventModal.jsx, resources/js/components/views/PhotoMosaicView.jsx, resources/js/components/views/views.css, tests/Feature/EventPhotosTest.php, tests/Feature/McpAuthorizationTest.php, AGENTS.md, CLAUDE.md, HANDOVER.md, public/build/*
OUT-OF-SCOPE: none

Storage: picked the JSON column (`events.image_urls`, nullable, additive) over an event_images table. The Event model's `image_urls` accessor reads a NULL column as `[image_url]` (or `[]`), so legacy rows are one-photo galleries with no data rewrite. `EventCreator::photoFields()` is the one place that keeps `image_url` = first entry; REST and MCP both route through it. A lone `image_url` from an older client replaces the cover only (null removes it), so it cannot wipe a gallery. Limit is `Event::MAX_PHOTOS` = 20, validated on REST store/update and MCP post/update.

Test-first: criteria 1, 2, 3, 5 were watched red for their own reason (list missing from the response, cover null, 201 instead of 422, no gallery on a legacy row). Criterion 4 (`test_a_non_owner_cannot_change_event_photos`, in McpAuthorizationTest) cannot be red before the build because the edit checks already existed; instead I removed the MCP `canEdit` check and then the REST 403 one by one and watched the test go red each time (MCP: errors empty; REST: 200 not 403), then restored both.

Criterion 6 NOT met: it needs a browser check of EventModal on timeline.test, and Herd serves C:\Dev\timeline, not this worktree. The pop-up now has prev/next buttons, arrow keys and an n/m counter; it still needs a person to look at it. The form also got a remove button per photo (otherwise a gallery could not be shrunk); no drag reorder, per the card.

Environment note: `npm run build` first failed because node_modules (here and in C:\Dev\timeline) lacks nsfwjs and @tensorflow/tfjs, which package.json/package-lock already list since card 0001. I ran `npm install` in this worktree only; package files did not change. Rob's checkout needs `npm install` before his next build.

The card's Plan says `.\vendor\bin\pest.bat`; this repo has no Pest, so the suite ran with `.\vendor\bin\phpunit.bat`. Pint passed.

**2026-10-05 to 2026-10-07** 24 reviews. Scope was sound every time. Every acceptance and breakage lens found the same defect. Running an MCP import again removes one photo each time. `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null`, even when the caller sent no photo. `EventCreator::importUpsert` then calls `applyUpdate`, and `photoFields` treats that null as "remove the cover" and runs `array_shift`. So a 5-photo gallery shrinks by one on every re-run. No test re-runs an import on a gallery. The `TimelineServer` instructions also still say "pass an empty string to remove one", but for `image_url` an empty string now removes only the cover. The reviews named the defect as "#1", but the criteria carried no numbers, so the loop could not reopen anything and bounced the card 24 times. The 24 review outputs were cut down to this entry on 2026-10-07; git has them.

**2026-10-07** Not a decision for Rob, so the card goes back to `todo/`. Criteria are now numbered, and the defect is #7. Fix: in `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it, using the same `array_intersect_key` that `image_urls` already uses. Then write `test_a_reimport_without_photo_fields_keeps_the_gallery`, and correct the `TimelineServer` instructions text. #6 is a browser look, which is agent work. Run `npm run build`, then open an event with several photos on https://timeline.test with Playwright.

