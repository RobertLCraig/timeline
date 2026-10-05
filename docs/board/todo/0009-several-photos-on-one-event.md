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
- [x] WHEN an event is saved with several photo URLs, THE APP SHALL store them in the order given and return them with the event. proves: `test_an_event_stores_several_photos_in_order`
- [x] WHEN an event has photos, THE APP SHALL keep `image_url` equal to the first one, so every existing view and API client still sees a cover photo. proves: `test_the_first_photo_is_the_cover_image`
- [x] WHEN an event is saved with more photos than the limit, THE APP SHALL refuse it with a validation error. proves: `test_an_event_with_too_many_photos_is_rejected`
- [x] WHEN a user who may not edit an event tries to change its photos, through REST or MCP, THE APP SHALL refuse. proves: `test_a_non_owner_cannot_change_event_photos`
- [x] WHEN an existing event with only `image_url` is read, THE APP SHALL return it as a one-photo gallery. proves: `test_a_legacy_event_reads_as_a_one_photo_gallery`
- [ ] WHEN the event pop-up opens on an event with several photos, THE APP SHALL let the user step through them. proves: manual - a browser check of `EventModal` on `timeline.test`
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

### 2026-10-05 review (v20261005062520-4a9a)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 32s, run by this job rather than reported by the card.

**acceptance: sound**

I checked each criterion against the code. I could not break any of them.

1. **Photos kept in order.** `App\Support\EventCreator::photoFields` uses `array_values` on the list you send. `App\Models\Event::imageUrls` stores the list as JSON. That keeps the order. REST and MCP both send their checked data through `EventCreator::create` and `applyUpdate`.
2. **First photo is the cover.** `photoFields` always returns `image_url = $urls[0]`. In `create`, the `+` operator keeps the `photoFields` values over the `null` defaults, so the cover is set correctly.
3. **More than 20 photos is refused.** The rule `max:Event::MAX_PHOTOS` is in `EventController::store` and `update`, and in `PostTimelineEventTool::handle` and `UpdateTimelineEventTool::handle`.
4. **Only the right people can change photos.** The photo change goes through the same edit check as every other field. The test `McpAuthorizationTest::test_a_non_owner_cannot_change_event_photos` tries both REST and MCP. It also checks that the database did not change.
5. **Old events show one photo.** When `image_urls` is NULL, the `Event::imageUrls` getter returns `[image_url]`.
6. **Step through photos in the pop-up.** This one is `proves: manual`, so it is not a finding. It waits for a person to check it in a browser.

VERDICT: sound

**scope: sound**

The work on card 0009 stays inside its fence. It does nothing extra that you need to fix.

**Findings**

- The diff I was given is very large, but most of it is from other cards. The changes to `UploadController` came from card 0006 (the Pint clean-up, commit `22c2bde`). The changes to `nsfwScan.js` came from card 0008 (commit `2cef4ec`). The invite email work (`GroupController::createInvite`, `InviteEmailTest`) came from card 0010 (commit `f7afd7e`).
- The commit that builds this card is `3213346`. It touches only the files that the card's Tasks name, plus the docs and `public/build/`. It does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. It keeps `album_url`.
- The migration `add_image_urls_to_events_table::up` is additive. It adds one nullable JSON column and does not change `image_url`.
- `Event::imageUrls` is an accessor on a real column, so REST responses include the list.
- It added one thing the card did not ask for: a button to remove each photo in `EventForm.jsx`. This is small, and without it you could not make a gallery smaller. It is not drag reordering, captions or per-photo visibility, so it does not go over the fence.
- Criterion #6 is open. It is a `manual` check, so it is not a finding. It is yours to try in the browser on `timeline.test`.

VERDICT: sound

**breakage: defect**

I found one real defect.

**Defect: re-running an MCP import eats one photo each time.**

- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null` to `EventCreator::importUpsert`. It does this even when the caller sent no `image_url`.
- When the `import_hash` matches an existing event, `applyUpdate` calls `photoFields`. That function sees `image_url` is null and runs `array_shift`. This removes the cover photo.
- So each re-run of an import with no photo fields removes one more photo. A 5-photo gallery becomes 4, then 3, and so on, and the cover changes each time.
- `importUpsert`'s docblock says a re-run import is "safe". That is now false.
- No test builds this case. `EventPhotosTest` never re-runs an import on an event that has a gallery.
- REST `store` is safe. `validate()` only returns the keys the request actually sent.

**Small stale doc:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

UNMET: #1 an MCP re-import (same `import_hash`) with no `image_url` sends null, so `photoFields` removes the first stored photo on every run, and the stored gallery does not stay as given.

VERDICT: defect

**acceptance**

- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import (same `import_hash`) with no `image_url` sends null, so `photoFields` removes the first stored photo on every run, and the stored gallery does not stay as given.

### 2026-10-05 review (v20261005080905-fd86)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 28s, run by this job rather than reported by the card.

**acceptance: defect**

**What I checked:** I traced each of the six criteria to the code.

- **#2, #3, #4 and #5 hold.**
  - #2: `EventCreator::photoFields` always sets `image_url` to `$urls[0]`.
  - #3: `max:Event::MAX_PHOTOS` is on REST `store` and `update`, and on the MCP post and update tools.
  - #4: photo changes go through the same edit check as every other field.
  - #5: the `Event::imageUrls` getter turns a NULL column into `[image_url]`.
- **#6 is a manual check.** It is open, but that is not a finding.

**One defect: #1 breaks on an MCP re-import.**

- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null` into `EventCreator::importUpsert`. It does this even when the caller sent no photo.
- When the `import_hash` matches an existing event, `applyUpdate` calls `EventCreator::photoFields`. That function sees the `image_url` key set to null. Then it runs `array_shift` and removes the first stored photo.
- So every re-run takes one more photo off the gallery, and the cover changes each time. The stored list does not stay in the order it was given.
- No test re-runs an import on an event that has a gallery.
- **Fix:** send `image_url` only when the caller sent it. Then add that test.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the saved gallery is not kept.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's limits, and I found nothing left half done.

- **What the card's build commit touched.** Commit `3213346` changes only the files that the card's Tasks name. It also updates the docs and `public/build/`.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The changes to those files in the big diff come from other cards: 0006, 0008 and 0010. The comment code comes from card 0011.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one column. That column can be empty (nullable). It does not change `image_url`.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` now has a remove button for each photo. Without it, you could not take photos out of an event. This is part of editing a list, so it is not over the limit.
- **Half done.** Every task has code behind it. The task boxes on the card are still not ticked, but that is only the card's paperwork. Criterion #6 needs a person to check it in a browser, so it is not a finding.
- **Not my question.** The breakage review already reported that an MCP re-import removes one photo each time. That is a bug, not a scope problem.

VERDICT: sound

**breakage: defect**

The bug the last review found is still there. Nothing has fixed it.

**Bug: running the same MCP import again deletes one photo each time.**

- In `PostTimelineEventTool::handle`, the tool always sends `'image_url' => $validated['image_url'] ?? null`. It does this even when the caller sent no photo fields.
- In `EventCreator::importUpsert`, when the `import_hash` matches an event that already exists, the data goes to `applyUpdate`.
- `applyUpdate` calls `EventCreator::photoFields`. That function sees that the `image_url` key exists and is null. So it runs `array_shift`, which removes the first photo.
- So each re-run with no photo fields deletes the cover photo. A 5-photo gallery goes to 4, then to 3. The cover changes each time.
- The `importUpsert` comment (docblock) says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has a gallery.

**Out-of-date doc:** the photo text in the `TimelineServer` instructions does not say what an empty `image_url` does now. It removes only the cover, and the other photos stay.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`. Then add a test that re-runs an import on an event with a gallery.

UNMET: #1 an MCP re-import (same `import_hash`) with no photo fields sends `image_url` as null, so `photoFields` removes the first stored photo on every run, and the gallery does not stay in the order given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the saved gallery is not kept.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import (same `import_hash`) with no photo fields sends `image_url` as null, so `photoFields` removes the first stored photo on every run, and the gallery does not stay in the order given.

### 2026-10-05 review (v20261005094052-d038)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 35s, run by this job rather than reported by the card.

**acceptance: defect**

I looked at the code again. The bug that the last two reviews found is still there. Nobody has fixed it.

**What is wrong (#1):**
- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null` to `EventCreator::importUpsert`. It does this even when the caller sends no photo.
- When the `import_hash` matches an event that is already saved, the update goes through `EventCreator::photoFields`.
- `photoFields` finds the `image_url` key with a null value. It then runs `array_shift`, which removes the first saved photo.
- So each time the same import runs again with no photo fields, the event loses one photo. A gallery of 5 photos goes to 4, then to 3. The cover photo changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria:**
- #2, #3, #4 and #5 are correct.
- #6 is a manual check, so it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. The tool already does this for `image_urls` with `array_intersect_key`. Then add a test that runs an import again on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's limits.

- **The card's build commit.** Commit `3213346` changes only the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the tests and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `nsfwScan.js`. Those changes in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one column. That column can be empty (nullable).
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so it is part of editing a list. It does not go over the fence.
- **Half done.** Every task has code behind it. The task boxes are not ticked, but that is only the card's paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug that removes one photo each time belongs to the breakage review. It is not a scope problem.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody fixed it.

**What breaks.** If you run the same MCP import again, it deletes one photo each time.

- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null`. It does this even when the caller sent no photo.
- `EventCreator::importUpsert` finds the same `import_hash` and sends the data to `applyUpdate`.
- `applyUpdate` calls `EventCreator::photoFields`. That code sees an `image_url` key that holds null, so it runs `array_shift`. This removes the first photo.
- Run it 5 times on a 5-photo event. All 5 photos are gone. The cover changes on each run.
- The `importUpsert` docblock says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has a gallery.

**Old note in the docs.** The photo text in the `TimelineServer` instructions does not say what an empty `image_url` does now. It removes only the cover. The other photos stay.

**Fix.** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. The tool already does this for `image_urls` with `array_intersect_key`, so use the same method. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005111407-7dd4)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 37s, run by this job rather than reported by the card.

**acceptance: defect**

The bug that the last three reviews found is still there. Nobody has fixed it.

**What breaks (#1)**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds a matching `import_hash`. It then calls `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key, and that key holds null. So it runs `array_shift($urls)`, which removes the first saved photo.
- Each time the same import runs again with no photo fields, the event loses one photo. A gallery of 5 photos goes to 4, then to 3. The cover photo changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria**

- #2: `photoFields` always sets `image_url` to `$urls[0]`. It holds.
- #3: `max:Event::MAX_PHOTOS` is on the REST and MCP post and update paths. It holds.
- #4: photo changes go through the same edit check as every other field. It holds.
- #5: the `Event::imageUrls` getter turns a NULL column into `[image_url]`. It holds.
- #6 is a manual check. It is open, but that does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope only. The work stays inside the card's fence.

- **Build commit `3213346`.** It changes only the files the card's Tasks name, plus the docs and `public/build/`.
- **The upload path.** `UploadController`, `POST /api/upload` and `nsfwScan.js` are not changed by this card. Their changes in the big diff come from other cards (0006, 0008, 0010, 0011, 0012).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds only one nullable column. Nullable means the column can be empty. It does not rewrite `image_url`.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. It is part of editing a list, so it stays inside the fence.
- **Docs.** The instructions in `TimelineServer` now describe `image_urls`.
- **Half done.** Every task has code behind it. Criterion #6 is a manual check, so it is not a finding.
- **Not this lens.** The bug where an MCP re-import deletes one photo each time is a breakage problem, not a scope problem. The breakage lens reports it.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody has fixed it. This is the fourth review to find it.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null` into `EventCreator::importUpsert`. It does this even when the caller sends no photo.
- Suppose the `import_hash` matches an event that is already saved. Then `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds an `image_url` key whose value is null. So it runs `array_shift` and removes the first saved photo.
- Each re-run removes one more photo. A gallery of 5 photos goes to 4, then 3. The cover photo changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is safe. That is not true now.
- No test runs an import again on an event that has several photos.

**The fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. The tool already does this for `image_urls` with `array_intersect_key`. Use the same method for `image_url`. Then add a test that runs the import twice on an event with several photos.

**Out-of-date doc:** the photo text in the `TimelineServer` instructions still says "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

