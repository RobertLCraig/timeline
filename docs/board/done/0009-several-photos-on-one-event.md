---
no_outward_effect: "browser" is a local Playwright look at timeline.test; nothing is deployed
---
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
- [x] #6 WHEN the event pop-up opens on an event with several photos, THE APP SHALL let the user step through them. proves: manual - a browser check of `EventModal` on `timeline.test`
- [x] #7 WHEN an MCP import is run again with the same `import_hash` and no photo fields, THE APP SHALL keep the stored gallery and cover unchanged. proves: `test_a_reimport_without_photo_fields_keeps_the_gallery`
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

**2026-10-07** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 24 times between todo and ai-review, which is the limit, so it is waiting on a person. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer reopens every criterion it reports unmet, and the reviews that sent this card back named no criterion they disproved, so it came back with 5 of 7 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Add or reopen the criterion the finding breaks and move it back to todo/, or say here why the finding is wrong.

### 2026-10-07 manager (m20261007060611-d39f)

**outcome: rob**

The loop parked this card at its bounce limit, and said: "**2026-10-07** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 24 times between todo and ai-review, which is the limit, so it is waiting on a person. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer reopens every criterion it reports unmet, and the reviews that sent this card back named no criterion they disproved, so it came back with 5 of 7 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Add or reopen the criterion the finding breaks and move it back to todo/, or say here why the finding is wrong." Sent back to todo/ it would be parked again before any build ran, so no manager session was started and it is yours.

**2026-10-11** Built #7 in an attended clear-down session (branch `cleardown/0009-0011-0012`, commit `511abf0`, fast-forwarded to `main`). `PostTimelineEventTool::handle` now passes `image_url` only when the caller sent it, through the same `array_intersect_key` as `image_urls`; the default `'image_url' => ... ?? null` line is gone. `TimelineServer` instructions now say an empty `image_urls` list removes every photo, an empty `image_url` removes only the cover, an empty `album_url` removes the album link, and a field left out keeps what is stored. New test `test_a_reimport_without_photo_fields_keeps_the_gallery` in `tests/Feature/EventPhotosTest.php` posts a 3-photo event over MCP, then re-posts the same `import_hash` twice with no photo fields. Watched red against the old tool: the gallery came back as `['/uploads/3.jpg']`. Green after. Suite: 62 tests, 269 assertions, all passed; `pint --test` passed.

#6 checked with Playwright on http://timeline.test, 1280px, after `npm run build`, logged in as the local admin. Event 3 in `demo` was given three photos for the look and restored afterwards. In the Photos view the pop-up opened at `1 / 3` on the first photo. Next, ArrowRight, ArrowRight (wraps to `1 / 3`), Previous (`3 / 3`) and ArrowLeft (`2 / 3`) each showed the matching image. Pass. Local DB note: the `image_urls` and `event_comments` migrations were pending in `C:\Dev\timeline` and were run (additive, local only). Moving to `ai-review/`.

### 2026-10-13 review (v20261013043234-a4b9)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 22s, run by this job rather than reported by the card.

**acceptance: sound**

I checked all 7 criteria against the code. I could not break any of them.

- **#1 (photos stored in order):** `EventCreator::photoFields` keeps the list in the order given (`array_values`). The `Event::imageUrls` accessor writes and reads it as JSON. REST and MCP send it through `create` / `applyUpdate`. `GetTimelineEventTool::handle` returns `image_urls`.
- **#2 (first photo is the cover):** `photoFields` always sets `image_url` to `$urls[0]`.
- **#3 (too many photos refused):** the limit is `Event::MAX_PHOTOS` (20). The `max:` rule uses it in `EventController::store`, `EventController::update`, and in `handle` in the MCP post and update tools.
- **#4 (non-owner cannot change photos):** the old edit checks still hold. The importUpsert path also checks the owner, through the authorizer in `PostTimelineEventTool::handle`.
- **#5 (old event reads as a one-photo gallery):** when the column is NULL, the `Event::imageUrls` accessor gives back `[image_url]`.
- **#6 (step through photos in the pop-up):** this is a manual check. The card records a Playwright pass, so it is not a finding.
- **#7 (re-import keeps the gallery):** fixed. `PostTimelineEventTool::handle` now sends `image_url` and `image_urls` only when the caller sent them (it uses `array_intersect_key`). With neither key, `photoFields` returns `[]` and `applyUpdate` does not touch the photos. The old bug, where each re-import removed one photo, cannot happen now.

VERDICT: sound

**scope: sound**

I found no scope problem with this card.

**What I checked**
- The card's own work is in two commits: `3213346` and `511abf0`. They touch only the files the card's Plan names, plus the docs and `public/build/`.
- Neither commit touches `UploadController` or `resources/js/lib/nsfwScan.js`, which the card fences off. Other cards changed those files: 0006 (Pint re-spacing, a code-format tool) and the pre-scan stall fixes.
- `album_url` is still there. There are no captions, no drag reorder and no per-photo visibility.
- The diff also holds comments, export/import, invite email and the phone install. Those come from cards 0010, 0011, 0012 and 0015, which went in on the same range. They are not this card's work.
- One extra: the form has a remove button for each photo. The card did not ask for it. But without it you cannot make a gallery smaller, so it is part of letting someone change an event's photos. It is not scope creep.
- Nothing is half done. Fix #7 is in `PostTimelineEventTool::handle`. It sends `image_url` and `image_urls` through `array_intersect_key`, so it passes only the photo fields the caller sent. The `TimelineServer` instructions text was corrected to match. The Tasks checkboxes are still empty, but the work for each one is in the commits.

No criterion is disproved.

VERDICT: sound

**breakage: sound**

I tried to break the fix for #7, and I could not.

**What I checked:**
- `PostTimelineEventTool::handle` now sends `image_url` and `image_urls` only when the caller sends them. It uses `array_intersect_key` to do this. A re-import with no photo fields therefore gives `EventCreator::photoFields` no photo key. That function then returns `[]`, so the gallery and the cover do not change.
- `EventCreator::applyUpdate` and `EventCreator::create` both use `photoFields`, so REST and MCP keep the same rule. `create` puts default values after the photo fields, and the `+` operator keeps the photo fields first, so the defaults do not overwrite them.
- The `TimelineServer` instructions now match what the code does for each photo field.

**One small thing that is not part of #7:**
- `PostTimelineEventTool::handle` always sends `album_url` (null when the caller leaves it out). This is old behaviour. So if you run an import again without `album_url`, the album link is removed. The instructions say "Leave a photo field out to keep what is stored", and an album link is not a photo field, so that text is not false.
- It does not break any criterion on this card. If you want the album link kept on a re-import, that needs a new card.

You do not need to do anything for this card. #6 is a manual check and the build session already did it.

VERDICT: sound

