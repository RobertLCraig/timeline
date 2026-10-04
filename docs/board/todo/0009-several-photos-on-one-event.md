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
- [ ] WHEN an event is saved with several photo URLs, THE APP SHALL store them in the order given and return them with the event. proves: `test_an_event_stores_several_photos_in_order`
- [ ] WHEN an event has photos, THE APP SHALL keep `image_url` equal to the first one, so every existing view and API client still sees a cover photo. proves: `test_the_first_photo_is_the_cover_image`
- [ ] WHEN an event is saved with more photos than the limit, THE APP SHALL refuse it with a validation error. proves: `test_an_event_with_too_many_photos_is_rejected`
- [ ] WHEN a user who may not edit an event tries to change its photos, through REST or MCP, THE APP SHALL refuse. proves: `test_a_non_owner_cannot_change_event_photos`
- [ ] WHEN an existing event with only `image_url` is read, THE APP SHALL return it as a one-photo gallery. proves: `test_a_legacy_event_reads_as_a_one_photo_gallery`
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
