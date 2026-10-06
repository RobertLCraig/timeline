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

### 2026-10-05 review (v20261005121956-5180)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 2s, run by this job rather than reported by the card.

**acceptance: defect**

The bug is still there. I checked the code again, and nobody has fixed it.

**What breaks (#1)**

- In `PostTimelineEventTool::handle`, the tool sends `image_urls` only when the caller sends it. It uses `array_intersect_key` to do this.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- When the `import_hash` matches a saved event, `EventCreator::importUpsert` calls `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It does find an `image_url` key that holds null, so it runs `array_shift`. That removes the first saved photo.
- Each re-run of the same import takes one more photo off. The cover photo changes each time. No test re-runs an import on an event with several photos.

**The other criteria**

- #2 works: `photoFields` always sets `image_url` to `$urls[0]`.
- #3 works: the REST and MCP post and update paths all check `max:Event::MAX_PHOTOS`.
- #4 works: photo changes go through the same edit check as every other field.
- #5 works: the `Event::imageUrls` getter turns a NULL column into `[image_url]`.
- #6 is a manual check, so it is not a finding.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Add `'image_url' => 1` to the `array_intersect_key` list. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside what the card asked for.

- **The card's build commit is `3213346`.** It changes only the files that the card's Tasks name, plus the docs and `public/build/`.
- **The upload path is not touched.** That commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The changes to those files in the big diff come from other cards (0006, 0008, 0010, 0011, 0012).
- **`EventModal.jsx`** changes by 139 lines in the big diff, but only 27 come from this card. The rest is the comments work from card 0011.
- **`album_url` is still there.** The migration `add_image_urls_to_events_table::up` only adds one column, and that column can be empty (nullable).
- **No fenced work was built.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing:** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller, so it is part of editing a list.
- **Nothing is half done.** Every task has code behind it. The task boxes are not ticked, but that is only the card's paperwork. #6 is a manual check, so it is not a finding.
- **Not my lens:** the MCP re-import bug, where each re-run removes one photo, belongs to the breakage review.

VERDICT: sound

**breakage: defect**

I checked the code again. The bug is still there. Nobody fixed it.

**Bug: if you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same call always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds the same `import_hash`. It then calls `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It does find an `image_url` key that is null. So it runs `array_shift($urls)`, which removes the first saved photo.
- Each re-run takes off one more photo. The cover photo (the first photo) changes each time. The `importUpsert` docblock says a re-run is "safe". That is now false.
- No test runs an import twice on an event that has several photos.

**Fix:** send `image_url` only when the caller sends it. Use the same `array_intersect_key` method. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005132631-0091)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

The bug that the earlier reviews found is still in the code. Nobody fixed it.

**What is wrong with #1**

- In `PostTimelineEventTool::handle`, the tool always sends `'image_url' => $validated['image_url'] ?? null`. It does this even when the caller sends no photo. It sends `image_urls` only when the caller sends it, through `array_intersect_key`.
- Then `EventCreator::importUpsert` finds an event with the same `import_hash`, so it runs an update.
- In `EventCreator::photoFields`, there is no `image_urls` key, but there is an `image_url` key and it holds null. So the code runs `array_shift($urls)`, and the first saved photo is removed.
- Each re-run removes one more photo, and the cover photo changes each time. So the event does not keep its photos in the order given.
- No test runs an import again on an event that has several photos.

**The other criteria**

- #2: `photoFields` always sets `image_url` to the first photo. This is correct.
- #3: the 20-photo limit is on the REST and MCP create and update paths. This is correct.
- #4: a photo change goes through the same edit check as every other field. This is correct.
- #5: the `Event::imageUrls` getter turns an empty column into a list with the one old photo. This is correct.
- #6 is a manual check, so it is not a finding.

**How to fix it:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use `array_intersect_key`, as the code already does for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope review of card 0009: no scope defect**

I checked the commit that builds this card, `3213346`. The work stays inside the card's limits.

- **Files it changes.** It changes only the files that the card's Tasks name:
  - the migration, `Event`, `EventCreator`, `EventController`, `TimelineServer`
  - the three MCP tools
  - `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`
  - the two test files and `public/build/`
  - the docs
- **The upload path.** It does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. Those changes in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds one column only. The column can be empty (nullable).
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. It is part of editing the list, so it stays inside the limits.
- **Half done.** Every task has code behind it. The task boxes on the card are not ticked yet. That is only the card's paperwork. Criterion #6 is a manual browser check, so it is not a finding.
- **Not part of this check.** The MCP re-import bug is still there. Each re-import deletes one photo. The breakage review owns that bug. It is not a scope problem.

VERDICT: sound

**breakage: defect**

I checked whether the bug is still in the code. It is still there.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It does this with `array_intersect_key`.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds the matching `import_hash` and calls `applyUpdate`.
- `applyUpdate` calls `EventCreator::photoFields`. That function finds no `image_urls` key. It does find an `image_url` key, and that key is null. So it runs `array_shift`, which deletes the first saved photo.
- Each re-run deletes one more photo, and the cover photo changes each time. After 5 runs, a 5-photo gallery has no photos left.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import again on an event that has several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use `array_intersect_key`, as the tool already does for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005145848-6fab)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

**The bug is still there.** It is the same one the last four reviews found.

- **#2, #3, #4 and #5 work.** Each one traces to real code:
  - #2: `EventCreator::photoFields` always sets `image_url` to `$urls[0]`.
  - #3: `max:Event::MAX_PHOTOS` is on the REST and MCP post and update paths.
  - #4: a photo change goes through the same edit check as every other field.
  - #5: the `Event::imageUrls` getter turns a NULL column into `[image_url]`.
- **#6 is a manual check.** It is not a finding. You must try it in a browser.

**What breaks (#1)**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. The data then goes to `EventCreator::photoFields`.
- `photoFields` sees `image_url` set to null and runs `array_shift`. This deletes the first saved photo.
- So each re-run of the same import deletes one more photo. The cover changes each time.
- No test runs an import twice on an event with several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use `array_intersect_key`, as the code already does for `image_urls`. Then add a test that runs the import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

The work stays inside what the card asked for. Nothing goes over the "Not this card" fence.

**What I checked:**
- **Build commit `3213346`.** It changes only the files the card's Tasks name, plus the docs and `public/build/`.
- **The upload path.** The commit does not touch `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The changes to those files in the big diff came from other cards (0006, 0008, 0010, 0011 and 0012).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one column. The column can be empty (nullable), so old rows keep working.
- **Things the card said not to build.** I found no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so I count it as part of editing a list.
- **Unfinished work.** Every task has code behind it. The task boxes on the card are still not ticked, but that is only the card's paperwork. Criterion #6 is a manual check, so it is not a finding.

**Not part of this check:** the bug where an MCP re-import removes one photo each time belongs to the breakage review. It is a bug, not a scope problem.

VERDICT: sound

**breakage: defect**

I checked the code again. The bug that the earlier reviews found is still not fixed.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sent it. It uses `array_intersect_key` to do this.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there, set to null, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash` and calls `applyUpdate`.
- In `EventCreator::photoFields`, there is no `image_urls`, but there is an `image_url` key set to null. So the code removes the first saved photo.
- Each re-run takes away one more photo, and the cover photo changes each time. The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import again on an event that has several photos.

**An old note in the docs:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover photo. The other photos stay.

**The fix:** send `image_url` only when the caller sent it, in the same way the code already does for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005162435-6fd0)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 4s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code again. The bug is still there. Nobody has fixed it.

**What breaks (#1)**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So the key is always there, and it holds null when the caller sent no photo.
- `EventCreator::importUpsert` finds a matching `import_hash`. Then the update goes to `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It finds an `image_url` key that holds null, so it runs `array_shift($urls)`. That removes the first saved photo.
- Each re-run of the same import with no photo fields removes one more photo. A gallery of 5 photos goes to 4, then 3. The cover photo changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria**

- #2, #3, #4 and #5 work as the card says.
- #6 is a manual check. It is open, but that is not a finding.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use `array_intersect_key`, the same way the tool already sends `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope of card 0009. The work stays inside the fence. I found nothing that grew past it.

- **Build commit `3213346`.** It changes only the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two test files and `public/build/`. It also updates `AGENTS.md`, `CLAUDE.md` and `HANDOVER.md`.
- **The upload path.** The commit does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. The changes to those files in the large diff come from other cards (0006, 0008, 0010, 0011, 0012).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one nullable column.
- **Fenced items.** It adds no captions, no drag-to-reorder and no per-photo visibility.
- **One extra.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. It is part of editing a list, so it does not go over the fence.
- **Half done.** Every task has code behind it. The Tasks boxes are not ticked, but that is only paperwork on the card. #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug that removes one photo on each run is a breakage finding, not a scope one.

VERDICT: sound

**breakage: defect**

I found that the bug is still there.

**Bug: running the same MCP import again deletes one photo each time.**

- In `PostTimelineEventTool::handle`, `image_urls` is sent only when the caller sends it. It uses `array_intersect_key` for that.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is always there, and it holds null when the caller sent no photo.
- `EventCreator::importUpsert` finds the matching `import_hash` and calls `applyUpdate`. That calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null, so it runs `array_shift($urls)`. This takes off the first saved photo.
- Each re-run with no photo fields deletes one more photo, and the cover changes each time. Five re-runs empty a 5-photo gallery.
- The `importUpsert` docblock still says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has a gallery.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method as `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005174600-49f3)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 25s, run by this job rather than reported by the card.

**acceptance: defect**

The photo bug is still there. Nobody fixed it.

**What breaks (#1)**
- In `PostTimelineEventTool::handle`, the tool sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key goes in as null, even when the caller sent no photo.
- When the `import_hash` matches a saved event, `EventCreator::importUpsert` calls `applyUpdate`. `applyUpdate` then calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null. So it runs `array_shift($urls)`, which removes the first saved photo.
- So each time you run the same import again, the event loses one photo. The cover photo changes each time.
- No test runs an import twice on an event that has more than one photo.

**The other criteria**
- #2 holds. `photoFields` sets `image_url` to `$urls[0]`.
- #3 holds. `max:Event::MAX_PHOTOS` is on the REST and MCP create and update paths.
- #4 holds. A photo change goes through the same edit check as every other field.
- #5 holds. The `Event::imageUrls` getter turns NULL into `[image_url]`.
- #6 is a manual check, so it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, add `image_url` to the `array_intersect_key` filter, so it goes in only when the caller sends it. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope only. The work stays inside the card's fence.

- **Build commit `3213346`.** It changes only the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the tests and `public/build/`. It also updates the docs (`AGENTS.md`, `CLAUDE.md`, `HANDOVER.md`).
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `nsfwScan.js`. Those changes in the big diff come from other cards (0006, 0008, 0010, 0011 and 0012).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds one nullable column (a column that can be empty). It does not change `image_url`.
- **Fenced-off features.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. It is part of editing a list, so it does not go over the fence.
- **Half-done work.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Outside my lens.** The MCP re-import bug removes one photo on each run. That is a breakage problem, not a scope problem. I found nothing in scope that disproves a criterion.

VERDICT: sound

**breakage: defect**

**The bug is still there. This is the fifth review that finds it.**

**What breaks:** If you run the same MCP import again, the event loses one photo each time.

- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null` to `EventCreator::importUpsert`. It does this even when the caller sent no photo.
- The `import_hash` matches an event that is already saved. So the data goes to `applyUpdate`, and then to `EventCreator::photoFields`.
- In `photoFields`, there is no `image_urls` key. But there is an `image_url` key, and its value is null. So it runs `array_shift`, and the first saved photo is removed.
- Run it 5 times on an event with 5 photos, and all 5 photos are gone. The cover photo also changes on each run.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. The tool already does this for `image_urls` with `array_intersect_key`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005190042-1b14)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code again. The bug that the last four reviews found is still there. Nobody has fixed it.

**Criteria that hold**
- **#2:** `EventCreator::photoFields` always sets `image_url` to `$urls[0]`.
- **#3:** the `max:Event::MAX_PHOTOS` rule is on REST `store` and `update`, and on the MCP post and update tools.
- **#4:** a photo change goes through the same edit check as every other field.
- **#5:** the `Event::imageUrls` getter turns a NULL column into `[image_url]`.
- **#6:** this is a manual check. It is open, but that is not a finding.

**Criterion that fails: #1**
- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same call always sends `'image_url' => $validated['image_url'] ?? null`. So the key is always there, even when the caller sends no photo.
- When `EventCreator::importUpsert` finds an event with the same `import_hash`, it calls `applyUpdate`, which calls `photoFields`.
- `photoFields` finds an `image_url` key that holds null. So it runs `array_shift`, which removes the first saved photo.
- Each time the same import runs again, the event loses one photo. The cover changes each time.
- No test runs an import again on an event that has several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Add it to the same `array_intersect_key` list as `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked only the scope: what the card asked for, and what it fenced off.

**What the build commit touched.** Commit `3213346` changes only the files that the card's Tasks name:
- the migration
- `Event` and `EventCreator`
- `EventController`
- the three MCP tools and `TimelineServer`
- `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx` and `views.css`
- the two test files
- `public/build/`

It also updates `AGENTS.md`, `CLAUDE.md` and `HANDOVER.md`.

**The fence ("Not this card").** The commit does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. `album_url` is still there. There are no captions, no drag-to-reorder and no per-photo visibility. The changes to those files in the big diff come from other cards (0006, 0008, 0010, 0011 and 0012).

**One extra thing.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so it stays inside the fence.

**Half done.** Every task has code behind it. Criterion #6 is a manual check, so it is not a finding.

**Not my lens.** The breakage review owns the MCP re-import bug. In that bug, `PostTimelineEventTool::handle` sends a null `image_url`, and each re-run of the import removes one photo. This scope check does not change that finding.

VERDICT: sound

**breakage: defect**

I checked the code again. The bug that every earlier review found is still there, and nobody has fixed it.

**What breaks: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It does this with `array_intersect_key`.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. The key is there with a null value even when the caller sent no photo.
- `EventCreator::importUpsert` finds the matching `import_hash`. It then calls `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key, but it does find `image_url`, and that holds null. So it runs `array_shift`. This removes the first saved photo.
- A 5-photo gallery goes to 4, then to 3. The cover photo changes on each run.
- The `importUpsert` docblock says a re-run is safe ("re-run safely"). That is now false.
- No test re-runs an import on an event that has several photos.

**Out-of-date doc:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, that now removes only the cover. The other photos stay.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. Use `array_intersect_key`, the same way the tool already does for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005194946-463a)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code again. The bug that earlier reviews found is still there. Nobody has fixed it.

**What I checked**

- **#2 works.** `EventCreator::photoFields` always makes `image_url` the first photo.
- **#3 works.** `max:Event::MAX_PHOTOS` is on REST `store` and `update`. It is also on the MCP post and update tools.
- **#4 works.** A photo change goes through the same edit check as every other field.
- **#5 works.** When the column is NULL, the `Event::imageUrls` getter gives back `[image_url]`.
- **#6 needs a person to check it in a browser.** It is open, but that is not a finding.

**What is broken (#1)**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So the key is null even when the caller sent no photo.
- On a re-import with the same `import_hash`, `EventCreator::importUpsert` calls `applyUpdate`, and that calls `photoFields`.
- `photoFields` sees the null `image_url` and runs `array_shift`. This removes the first saved photo.
- So each re-run takes one more photo off the event, and the cover changes each time.
- No test runs the same import again on an event with several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked only the scope: what the card did beyond what it asked for, and what it left half done. I found no scope problem.

- **The build commit.** Commit `3213346` changes 21 files. They are the files the card's Tasks name, plus the docs and `public/build/`.
- **The fence.** The commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The changes to those files in the big diff come from other cards (0006, 0008, 0010, 0011, 0012).
- **`album_url`.** It stays.
- **The migration.** `add_image_urls_to_events_table::up` adds one nullable column (a column that can be empty). It drops nothing and does not rewrite `image_url`.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One small extra.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so it is part of editing a list. It does not cross the fence.
- **Half done.** Every task has code behind it. The task boxes are not ticked, but that is only paperwork. Criterion #6 is a manual browser check, so it is not a finding.
- **Not my lens.** One bug is still open: an MCP re-import removes one photo each time. That belongs to the breakage review, not to scope, so I name no criterion here.

VERDICT: sound

**breakage: defect**

I checked the code. The bug from the last four reviews is still there. Nobody has fixed it.

**Bug: when you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` to do that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is always there. It holds null when the caller sends no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. Then `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It does find an `image_url` key that holds null. So it runs `array_shift($urls)`, and that removes the first saved photo.
- Each re-run removes one more photo. A gallery of 5 photos goes to 4, then to 3. The cover photo changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import twice on an event that has several photos.

**Old doc:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005210246-5ba6)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 26s, run by this job rather than reported by the card.

**acceptance: defect**

I found that the photo bug is still there. Nobody has fixed it yet.

**What goes wrong (#1)**
- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. The key holds null even when the caller sent no photo.
- A second run with the same `import_hash` goes through `EventCreator::importUpsert`, then to `applyUpdate`, then to `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It finds `image_url` set to null. So it runs `array_shift($urls)`, which deletes the first saved photo.
- Each new run deletes one more photo, and the cover photo changes each time.
- No test runs an import twice on an event that has several photos.

**The other criteria**
- #2: holds. `photoFields` sets `image_url` to `$urls[0]`.
- #3: holds. `max:Event::MAX_PHOTOS` is on the REST and MCP post and update paths.
- #4: holds. A photo change goes through the same edit check as every other field.
- #5: holds. The `Event::imageUrls` getter turns NULL into `[image_url]`.
- #6: this is a manual check. It is open, but it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool uses for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

The work stays inside the card's limits. I found nothing that needs a fix.

- **What I checked.** I looked at the card's build commit, `3213346`. It changes only the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two tests and `public/build/`. It also updates `AGENTS.md`, `CLAUDE.md` and `HANDOVER.md`.
- **The upload path.** The commit does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. The changes to those files in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so it does not go over the fence.
- **Half done.** Every task has code behind it. The task boxes are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug belongs to the breakage review. That bug is in `PostTimelineEventTool::handle`: it removes one photo on each re-run.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody has fixed it.

**Defect: when you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. But it always sends `'image_url' => $validated['image_url'] ?? null`, even when the caller sends no photo.
- `EventCreator::importUpsert` finds a matching `import_hash`. Then `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds an `image_url` key that holds null. It runs `array_shift($urls)`, which deletes the first saved photo.
- Each re-run with no photo fields deletes one more photo. The cover photo changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has several photos.

**Old text in the docs:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005220145-24ae)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 24s, run by this job rather than reported by the card.

**acceptance: defect**

The bug from the last four reviews is still in the code. Nobody has fixed it.

**What breaks: running the same MCP import again deletes one photo each time (#1).**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It does this with `array_intersect_key`.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. It sends the data on through `applyUpdate` to `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null, so it runs `array_shift`. This removes the first saved photo.
- Each re-run takes one more photo off the event. The cover photo changes each time.
- No test runs an import twice on an event that has several photos.

**The other criteria:**

- #2, #3, #4 and #5 work.
- #6 is a manual check, so it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that `image_urls` uses. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's fence.

- **The build commit.** Commit `3213346` changes only the files that the card's Tasks name. These are the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two test files and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `nsfwScan.js`. Other cards made those changes in the big diff: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds only one nullable column. "Nullable" means the column can be empty.
- **Things the card said not to build.** The commit adds no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. It is part of editing a list, so it stays inside the fence.
- **Half done.** Each task has code behind it. Nobody ticked the task boxes, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug in `PostTimelineEventTool::handle` removes one photo on each run. It belongs to the breakage review. It is not a scope problem, so I write no `UNMET:` line for it here.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody fixed it.

**What breaks: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it, because it uses `array_intersect_key`.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- When `EventCreator::importUpsert` finds a matching `import_hash`, it calls `applyUpdate`, and `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null, so it runs `array_shift($urls)`. That removes the first saved photo.
- Each re-run takes one more photo off. A 5-photo gallery goes to 4, then 3. The cover photo changes each time.
- The `importUpsert` docblock says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has a gallery.

**Out-of-date doc:** the MCP server instructions still say "pass an empty string to remove one". For `image_url`, that now removes only the cover, and the other photos stay.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-05 review (v20261005230903-7c44)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 24s, run by this job rather than reported by the card.

**acceptance: defect**

I looked at the code again. The bug is still there. Nobody fixed it.

**Criterion #1 is broken: a repeated MCP import removes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller gives it. It does this with `array_intersect_key`.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there, set to null, even when the caller gives no photo.
- When the `import_hash` matches a saved event, `EventCreator::importUpsert` calls `applyUpdate`. That calls `EventCreator::photoFields`.
- `photoFields` does not find `image_urls`. It finds `image_url` set to null. So it runs `array_shift`, and the first saved photo is removed.
- Each re-run removes one more photo. The cover photo changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria:**

- #2, #3, #4 and #5 are correct.
- #6 is a manual check. It is open, but it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller gives it. Do it the same way as for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's fence.

- **The build commit.** Commit `3213346` changes these files, and the card's Tasks name all of them:
  - the migration
  - `Event` and `EventCreator`
  - `EventController`
  - the three MCP tools and `TimelineServer`
  - `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx` and `views.css`
  - the two test files
  - `public/build/`

  It also updates the docs (`AGENTS.md`, `CLAUDE.md`, `HANDOVER.md`).
- **The upload path.** The commit does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. The changes to those files in the big diff come from other cards (0006, 0008, 0010, 0011 and 0012).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one nullable column. "Nullable" means the column can be empty.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you could not make a gallery smaller. It is part of editing a list, so it does not go over the fence.
- **Half done.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug removes one photo on each run. It belongs to the breakage review, not to scope.

My finding disproves no criterion.

VERDICT: sound

**breakage: defect**

The same bug is still there. Nobody has fixed it.

**Bug: if you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there, with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. It sends the data to `applyUpdate`, and `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It does find an `image_url` key that holds null. So it runs `array_shift`, which removes the first saved photo.
- Each re-run removes one more photo, and the cover photo changes each time. Five re-runs on a 5-photo event remove all 5 photos.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import again on an event that has several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method the function already uses for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006000838-63f9)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 23s, run by this job rather than reported by the card.

**acceptance: defect**

**Criteria #2 to #5 hold:**
- **#2:** `EventCreator::photoFields` always makes the cover photo (`image_url`) the first photo in the list.
- **#3:** The 20-photo limit (`max:Event::MAX_PHOTOS`) is checked in `EventController::store` and `update`. It is also checked in the MCP post and update tools.
- **#4:** A photo change goes through the same edit check as any other change.
- **#5:** The `Event::imageUrls` getter reads an empty `image_urls` column as `[image_url]`. So an old event shows as a one-photo gallery.

**#6** is a manual browser check. It is open, but that does not count against the card.

**#1 is still broken.** This is the fifth review that finds it.
- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So a call with no photos still sends `image_url` as null.
- A re-run import (same `import_hash`) goes from `EventCreator::importUpsert` to `applyUpdate` to `EventCreator::photoFields`.
- `photoFields` sees the null `image_url` and runs `array_shift`. That deletes the first saved photo.
- So each re-run deletes one more photo. The cover photo changes each time.
- No test runs an import twice on an event with several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` call the tool already uses for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope. It is sound.

- **Build commit `3213346`.** It changes only the files that the card's Tasks name. These are the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two tests and `public/build/`. It also updates the docs.
- **The fence.** The commit does not touch `UploadController`, `POST /api/upload` or `nsfwScan.js`. Those changes in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012. `album_url` is still there. There are no captions, no drag-to-reorder and no per-photo visibility.
- **The migration.** `add_image_urls_to_events_table::up` adds one column that can be empty (nullable). It does not change `image_url`.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, a user could not make a gallery smaller, so it belongs to editing the list. It does not go over the fence.
- **Half done.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug that removes one photo on each run is a breakage problem. It is not a scope problem. This lens does not disprove any criterion.

VERDICT: sound

**breakage: defect**

I found one bug. Nobody has fixed it yet.

**The bug: if you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key goes in as null, even when the caller sent no photo.
- `EventCreator::importUpsert` finds the same `import_hash`. It then calls `EventCreator::photoFields`.
- `photoFields` finds an `image_url` key that holds null. So it runs `array_shift`, which removes the first saved photo.
- Each re-run takes one more photo away. The cover photo changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import twice on an event that has several photos.

**The fix:**
1. In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`.
2. Add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006010205-903b)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 28s, run by this job rather than reported by the card.

**acceptance: defect**

Criterion #1 is still broken. This is the fifth review to find this bug, and nobody has fixed it.

**What breaks (#1)**
- In `PostTimelineEventTool::handle`, the tool sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- Sometimes the `import_hash` matches an event that is already saved. Then `EventCreator::importUpsert` calls `applyUpdate`, and `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null. So it runs `array_shift`, which removes the first saved photo.
- Each re-run of the same import takes off one more photo, and the cover photo changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria**
- #2, #3, #4 and #5 hold. The reasons are the same as in the earlier reviews.
- #6 is a manual check. It is still open, but it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Put it in the same `array_intersect_key` call. Then add a test that runs an import twice on an event that has several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` runs `array_shift` and removes the first saved photo on every run.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's fence.

- **The build commit.** Commit `3213346` changes only the files that the card's Tasks name. These are the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two test files and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The changes to those files in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds one nullable column. Nullable means it can be empty. The migration does not rewrite `image_url`.
- **Things the card fenced off.** The commit adds no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, nobody could make a gallery smaller. It is part of editing a list, so it stays inside the fence.
- **Half done.** Every task has code behind it. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug belongs to the breakage review. In that bug, each re-run of the same import removes one photo. It is not a scope problem.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody has fixed it.

**What breaks.** Each time you run the same MCP import again, the event loses one photo.

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key goes in with a null value, even when the caller sends no photo.
- `EventCreator::importUpsert` finds the matching `import_hash`. It then calls `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` sees the null `image_url` key and runs `array_shift`. That removes the first saved photo.
- So a 5-photo gallery goes to 4, then to 3. The cover photo changes each time.
- The `importUpsert` docblock says a re-run is "safe". That is now false.
- No test runs an import twice on an event that has several photos.

**Out-of-date note.** The `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover photo. The other photos stay.

**Fix.** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` step that already handles `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` runs `array_shift` and removes the first saved photo on every run.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006015542-c960)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

Criterion #1 is still broken. This is the fifth review to find the same bug, and the code has not changed.

**What I checked:**
- **#2 works.** `EventCreator::photoFields` always sets `image_url` to the first photo.
- **#3 works.** `max:Event::MAX_PHOTOS` is on the REST store and update routes, and on the MCP post and update tools.
- **#4 works.** A photo change goes through the same edit check as every other field.
- **#5 works.** When `image_urls` is empty, the `Event::imageUrls` getter gives back `[image_url]`.
- **#6 is a manual check.** It waits for a person to try it in a browser, so it does not count against the card.

**What is broken (#1):**
- `PostTimelineEventTool::handle` always sends `'image_url' => $validated['image_url'] ?? null`. It does this even when the caller sent no photo.
- Suppose the `import_hash` matches an event that is already saved. Then `EventCreator::importUpsert` sends the data on to `photoFields`.
- `photoFields` finds the `image_url` key with a null value. So it runs `array_shift`, and that removes the first saved photo.
- Each re-run of the same import takes off one more photo, and the cover photo changes each time.
- No test re-runs an import on an event that has several photos.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. The tool already does this for `image_urls` with `array_intersect_key`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

The scope check is sound. The work stays inside the card's limits.

- **The build commit.** Commit `3213346` is the commit that does the work. It changes only the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the tests and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. Those changes in the big diff come from other cards (0006, 0008, 0010, 0011 and 0012).
- **`album_url`** is still there.
- **The migration** (`add_image_urls_to_events_table::up`) only adds one nullable column. "Nullable" means the column can be empty.
- **Fenced-off items.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button on each photo. You need it to make a gallery smaller, so it is part of editing a list. It is not over the fence.
- **Half done.** Every task has code behind it. The task boxes are not ticked, but that is only paperwork. #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug (`PostTimelineEventTool::handle` always sends `image_url`) is a breakage problem. It is not a scope problem.

VERDICT: sound

**breakage: defect**

The bug is still there. Nobody has fixed it.

**Bug: if you run the same MCP import again, it deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it, because it uses `array_intersect_key`.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with null in it, even when the caller sent no photo.
- `EventCreator::importUpsert` finds the matching `import_hash` and calls `applyUpdate`.
- `EventCreator::photoFields` finds no `image_urls`. It finds `image_url` set to null, so it runs `array_shift`. That removes the first saved photo.
- Each re-run removes one more photo, and the cover changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import again on an event that has several photos.

**Out-of-date doc:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. Use `array_intersect_key`, the same way the function already does for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006023342-bff1)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 26s, run by this job rather than reported by the card.

**acceptance: defect**

The bug that earlier reviews found is still there. I checked the code again.

**What breaks (#1)**
- `PostTimelineEventTool::handle` passes `image_urls` on only when the caller sends it. It uses `array_intersect_key` to do this.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is always there. It holds null when the caller sends no photo.
- When the `import_hash` matches a saved event, `EventCreator::importUpsert` sends the data to `applyUpdate`. `applyUpdate` then calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It finds `image_url` set to null, so it runs `array_shift`. That removes the first saved photo.
- Each time the same import runs again, the event loses one photo. The cover photo also changes each time.
- No test runs an import twice on an event that has several photos.

**Criteria that hold**
- #2: `photoFields` sets `image_url` to `$urls[0]`.
- #3: the `max:Event::MAX_PHOTOS` rule is on REST store and update, and on the MCP post and update tools.
- #4: a photo change goes through the same edit check as every other field.
- #5: the `Event::imageUrls` getter turns a NULL column into `[image_url]`.
- #6: this is a manual check, so it is not a finding.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

**Scope check: sound.** The work stays inside the card's fence.

- **What the build changed.** Commit `3213346` changes only the files that the card's Tasks name. Those are the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two test files and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not touch `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. The big diff does show changes to them, but those came from cards 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` only adds one nullable column (a column that may be empty). It does not change `image_url`.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, nobody could make a gallery smaller. It is part of editing a list, so it stays inside the fence.
- **Half done.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not my lens.** The MCP re-import bug removes one photo each time. The breakage review owns that bug, and it is still open.

VERDICT: sound

**breakage: defect**

I checked the code again. The bug is still there. Nobody fixed it.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` to do this.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is there, set to null, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. It sends the data to `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key that holds null. So it runs `array_shift($urls)`, which removes the first saved photo.
- Each re-run of the import removes one more photo. A gallery of 5 photos goes to 4, then to 3. The cover photo changes each time.
- The `importUpsert` docblock (the comment above the function) says a re-run is "safe". That is now false.
- No test runs an import again on an event that has several photos.

**The fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Add `image_url` to the `array_intersect_key` list that `image_urls` already uses. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006143723-b2eb)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 21s, run by this job rather than reported by the card.

**acceptance: defect**

I traced each of the six criteria to the code. Five hold. Criterion #1 does not hold, because the bug from the last reviews is still there.

**Criteria that hold**
- **#2:** `EventCreator::photoFields` always sets `image_url` to the first entry in the list.
- **#3:** the rule `max:Event::MAX_PHOTOS` is on four save paths. These are `EventController::store` and `update`, and `PostTimelineEventTool::handle` and `UpdateTimelineEventTool::handle`.
- **#4:** a photo change goes through the same edit check as every other field.
- **#5:** the `Event::imageUrls` getter turns an empty (NULL) column into `[image_url]`.
- **#6:** this is a manual check. It is open, but that is not a finding.

**Criterion that fails: #1**
- `PostTimelineEventTool::handle` sends `image_urls` to `EventCreator::importUpsert` only when the caller sends it.
- But the same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- When the `import_hash` matches a saved event, `applyUpdate` calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It finds `image_url` set to null, so it runs `array_shift`. This removes the first saved photo.
- So each re-run of the same import with no photos removes one more photo, and the cover photo changes each time.
- No test runs an import twice on an event that has several photos.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method that the tool already uses for `image_urls`. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope only. The work stays inside the card's limits.

- **Build commit `3213346`.** It changes only the files that the card's Tasks name. These are the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the tests and `public/build/`. It also updates the docs.
- **The upload path.** The commit does not change `UploadController`, `POST /api/upload` or `nsfwScan.js`. The changes to those files in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds one column only. That column can be empty (nullable).
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a remove button for each photo. Without it, you cannot make a gallery smaller. So it is part of editing a list, and it stays inside the fence.
- **Half done.** Every task has code behind it. Criterion #6 is a manual check, so it is not a finding.
- **Not my question.** A re-run of an MCP import in `PostTimelineEventTool::handle` removes one photo each time. That is a bug, and the breakage review owns it. It is not a scope problem.

VERDICT: sound

**breakage: defect**

The MCP re-import bug is still in the code. Nobody has fixed it.

**What breaks**

- In `PostTimelineEventTool::handle`, the tool only passes `image_urls` on when the caller sent it. It uses `array_intersect_key` to do that.
- It does not do the same for `image_url`. It always sends `'image_url' => $validated['image_url'] ?? null`. So when the caller sends no photo, the key is still there, set to null.
- `EventCreator::importUpsert` finds the matching `import_hash`. It then sends the data to `applyUpdate`, and that goes to `EventCreator::photoFields`.
- `photoFields` finds no `image_urls`. It does find an `image_url` key set to null. So it takes the first stored photo off the list (the code does this with `array_shift`).
- So each re-run of the same import with no photo fields deletes one photo. After 5 re-runs, a 5-photo gallery has no photos. The cover photo changes on each run.
- The docblock on `importUpsert` says a re-run is "safe". That is no longer true.
- No test re-runs an import on an event that has several photos.

**Old doc text:** the photo text in the `TimelineServer` instructions does not say what an empty `image_url` does now. It removes only the cover photo. The other photos stay.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sent it. Use `array_intersect_key`, the same way the tool already does for `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first stored photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006160830-4b10)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 22s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code. The bug that the earlier reviews found is still there. Nobody has fixed it.

**Bug (#1): running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for that.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sends no photo.
- `EventCreator::importUpsert` finds the matching `import_hash` and sends the data to `applyUpdate`.
- `applyUpdate` calls `EventCreator::photoFields`. That function finds no `image_urls`. It does find `image_url`, and its value is null. So it runs `array_shift`, which removes the first saved photo.
- Each re-run takes one more photo off the gallery, and the cover changes each time.
- No test runs an import again on an event that has several photos.

**The other criteria:**
- #2, #3, #4 and #5 work.
- #6 is a manual check. It is still open, but that does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked the scope only. The work stays inside the card's fence.

**What the card's build commit changed.** I read the file list of commit `3213346`. It changes only:
- the files that the card's Tasks name: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools, `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the tests and `public/build/`
- the docs.

**The fence.**
- The commit does not touch `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`.
- No later commit changes those files for this card. Their changes in the big diff come from cards 0006, 0008, 0010, 0011 and 0012.
- `album_url` stays.
- The migration `add_image_urls_to_events_table::up` only adds one nullable column. Nullable means the column can be empty.
- The card did not want captions, drag-to-reorder or per-photo visibility. None of them are here.

**One extra thing.** `EventForm.jsx` has a remove button on each photo. Without it, you cannot make a gallery smaller. It belongs to editing a list, so it does not go over the fence.

**Half done.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 needs a person to check it in a browser, so it is not a finding.

**Not my question.** Other reviews found a bug: running the same MCP import again deletes one photo each time. That is a breakage problem, not a scope problem.

VERDICT: sound

**breakage: defect**

I checked the code again. The old bug is still there. Nobody fixed it.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` for this.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds a matching `import_hash`. It then sends the data to `applyUpdate`, which calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It does find an `image_url` key that holds null, so it runs `array_shift($urls)`. That removes the first saved photo.
- Each re-run takes off one more photo. The cover photo changes each time. After 5 re-runs, a 5-photo gallery has no photos left.
- The `importUpsert` docblock says a re-run is "safe". That is now false.
- No test re-runs an import on an event that has several photos.

**Fix:**
1. In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use `array_intersect_key` with both keys.
2. Add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006180638-d966)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 34s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code. The bug from the last four reviews is still there.

**Criterion #1 fails: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It uses `array_intersect_key` to do this.
- But the same call always sends `'image_url' => $validated['image_url'] ?? null`. So the `image_url` key is always there, with null in it, even when the caller sent no photo.
- `EventCreator::importUpsert` finds an event with the same `import_hash`. It passes the data on to `EventCreator::photoFields`.
- In `photoFields`, there is no `image_urls`, but the `image_url` key is there and it is null. So the code runs `array_shift($urls)`, which deletes the first saved photo.
- Each time the same import runs again, the event loses one more photo, and the cover photo changes. So the saved gallery does not stay as it was given.

**The other criteria:** #2, #3, #4 and #5 work. `photoFields` makes the first photo the cover. `max:Event::MAX_PHOTOS` refuses more than 20 photos. Changing photos goes through the normal edit permission check. The `Event::imageUrls` getter shows an old single-photo event as a gallery of one. #6 is a manual check, so it does not count against the card.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it, with the same `array_intersect_key` method. Then add a test that runs the same import twice on an event that has several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run.

VERDICT: defect

**scope: sound**

I checked only the scope: did this card build things it was not asked for, and did it leave anything half done?

**What I did:** I read the card's build commit, `3213346`. I also checked the recent history of `PostTimelineEventTool` and `EventCreator`.

**What I found:**

- **Build commit `3213346`.** It touches 21 files: the migration, `Event`, `EventCreator`, `EventController`, the three MCP tools (`PostTimelineEventTool`, `UpdateTimelineEventTool`, `GetTimelineEventTool`), `TimelineServer`, `EventForm.jsx`, `EventModal.jsx`, `PhotoMosaicView.jsx`, `views.css`, the two test files, the docs and `public/build/`. The card's Tasks cover all of them.
- **The fence ("Not this card").** The commit does not change `UploadController`, `POST /api/upload` or `nsfwScan.js`. `album_url` is still there. The other changes in the big diff come from other cards: 0006, 0008, 0010, 0011 and 0012.
- **The migration.** `add_image_urls_to_events_table::up` only adds one column, and that column can be empty. It does not change `image_url`.
- **Banned extras.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra.** `EventForm.jsx` has a remove button for each photo. You need it to make a gallery smaller, so it is part of editing a list. It does not cross the fence.
- **Half done.** Every task has code behind it. The task boxes are not ticked, but that is only the card's paperwork. #6 is a manual check that waits for you.
- **Not my lens.** The MCP re-import bug removes one photo each time. It belongs to the breakage review. It is not a scope problem, so I name no criterion here.

**What you do now:** nothing for scope. The re-import bug from the other reviews is still the open fix.

VERDICT: sound

**breakage: defect**

**What I checked:** I read `PostTimelineEventTool::handle` and `EventCreator::photoFields`. The bug from the last four reviews is still there. Nobody fixed it.

**The bug: running the same MCP import again deletes one photo each time.**

- `PostTimelineEventTool::handle` passes `image_urls` on only when the caller sends it, using `array_intersect_key`.
- But it always sends `'image_url' => $validated['image_url'] ?? null`. So the key is there and set to null, even when the caller sent no photo.
- When the `import_hash` matches an event that already exists, `EventCreator::importUpsert` calls `applyUpdate`, which calls `photoFields`.
- `photoFields` sees an `image_url` key that holds null. It runs `array_shift` and deletes the first saved photo.
- Each run of the import deletes one more photo, and the cover photo changes each time.
- No test runs an import twice on an event that has several photos.
- The `importUpsert` docblock says a re-run is "safe". That is false now.

**Out-of-date doc:** the photo text in the `TimelineServer` instructions still says "pass an empty string to remove one". It does not mention `image_urls`.

**Fix:** In `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Use the same `array_intersect_key` method. Then add a test that runs an import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` deletes the first saved photo on every run and the gallery is not kept as given.

### 2026-10-06 review (v20261006200941-861c)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 33s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code again. The bug is still there.

**#2, #3, #4 and #5 hold.**
- #2: `EventCreator::photoFields` always sets `image_url` to `$urls[0]`.
- #3: `max:Event::MAX_PHOTOS` is on REST `store` and `update`, and on the MCP post and update tools.
- #4: a photo change goes through the same edit check as every other field.
- #5: the `Event::imageUrls` getter turns a NULL column into `[image_url]`.

**#6 is a manual check.** It is open, and that is not a finding.

**#1 is broken by an MCP re-import.**
- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it. It does this with `array_intersect_key`.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`.
- `EventCreator::importUpsert` finds the same `import_hash` and calls `applyUpdate`. That calls `photoFields`.
- `photoFields` finds no `image_urls`, but it finds an `image_url` key that is null. So it runs `array_shift` and removes the first saved photo.
- Each re-run removes one more photo, so the saved gallery does not stay as it was given.
- No test re-runs an import on an event that has several photos.

**Fix:** send `image_url` only when the caller sends it, the same way as `image_urls`. Then add a test that runs the same import twice on an event with several photos.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**scope: sound**

I checked only the scope. The build stays inside the card's fence.

- **What the build commit changed.** Commit `3213346` changes only the files the card's Tasks name. It also updates the docs and `public/build/`.
- **The upload path.** That commit does not change `UploadController`, `POST /api/upload` or `resources/js/lib/nsfwScan.js`. Those changes in the big diff come from earlier commits on other cards: `22c2bde` (card 0006) and `2cef4ec` (card 0008).
- **`album_url`.** It is still there.
- **The migration.** `add_image_urls_to_events_table::up` adds one column that can be empty (nullable). It does not change `image_url`.
- **Things the card said not to build.** There are no captions, no drag-to-reorder and no per-photo visibility.
- **One extra thing.** `EventForm.jsx` has a button to remove each photo. Without it, you cannot make a gallery smaller. It is part of editing a list, so it does not go over the fence.
- **Half-done work.** Every task has code behind it. The task boxes on the card are not ticked, but that is only paperwork. Criterion #6 is a manual check, so it is not a finding.
- **Not part of this check.** The open MCP re-import bug removes one photo on each run. It sits in `PostTimelineEventTool::handle` and `EventCreator::photoFields`. That is a breakage problem, not a scope problem. The other reviews already reported it against #1.

VERDICT: sound

**breakage: defect**

I checked the code again. The bug that the last reviews found is still there. Nobody has fixed it.

**Bug: a second run of the same MCP import deletes one photo each time.**

- `PostTimelineEventTool::handle` sends `image_urls` only when the caller sends it, because it uses `array_intersect_key`.
- The same function always sends `'image_url' => $validated['image_url'] ?? null`. So the key is present with a null value, even when the caller sent no photo.
- `EventCreator::importUpsert` finds the matching `import_hash` and calls `applyUpdate`. That calls `EventCreator::photoFields`.
- `photoFields` finds no `image_urls` key. It finds `image_url` with a null value, so it runs `array_shift`. That removes the first saved photo.
- Each run with no photo fields deletes one more photo, and the cover photo changes each time. A gallery of 5 photos is empty after 5 runs.
- The `importUpsert` docblock says a re-run is "safe". That is false now.
- No test runs an import twice on an event that has several photos.

**Out-of-date doc:** the `TimelineServer` instructions still say "pass an empty string to remove one". For `image_url`, an empty string now removes only the cover. The other photos stay.

**Fix:** in `PostTimelineEventTool::handle`, send `image_url` only when the caller sends it. Then add a test that runs the import twice.

UNMET: #1 an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

VERDICT: defect

**acceptance**

- **#1 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields still sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.
- **#1 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: an MCP re-import with the same `import_hash` and no photo fields sends `image_url` as null, so `EventCreator::photoFields` removes the first saved photo on every run and the gallery is not kept as given.

