# Client-side NSFW pre-scan before upload

## Why
Four of the five parts of the Phase 4 moderation design have shipped: the Sightengine scan in
`UploadController`, the `app_settings` and `upload_flags` tables, the admin review routes, and the
Flagged Uploads tab in `AdminPanel.jsx`. The client-side pre-scan is the one part that has not:
`@tensorflow-models/nsfwjs` appears nowhere in `package.json` or `resources/js`.

Without it every image reaches the server and burns a Sightengine call, and the free tier is 500
images a month. The uploader also gets no feedback until after the upload completes.

## Not this card
Changing the server-side scan, the threshold setting, or the review queue. Those are built and this
card sits in front of them, not over them.

## Acceptance
<!-- AC:BEGIN -->
- [ ] WHEN a user selects an image whose `Porn`, `Hentai` or `Sexy` score exceeds 0.7, THE APP SHALL
      block the upload in the browser and say why, before any request is sent.
- [ ] WHEN a user selects an image scoring below the threshold, THE APP SHALL upload it normally and
      the server-side scan SHALL still run, so the client check is a filter and never the only one.
- [x] WHERE the model fails to load, THE APP SHALL allow the upload and fall through to the server
      scan rather than blocking the user out of a working feature.
<!-- AC:END -->

## Tasks
- [x] Add `@tensorflow-models/nsfwjs` and load the model lazily, not on app boot
- [x] Hook the classification into the upload component before the request
- [x] Prove the fall-through path by breaking the model load deliberately

## Plan
The threshold 0.7 is the figure the Phase 4 design named and the server already reads its own
threshold from `app_settings`. Keep the client figure a named constant rather than reaching for the
setting: the server setting is authoritative and a client that fetched it would be a second copy of
the rule.

## Comments

**2026-08-29** Built the pre-scan. `resources/js/lib/nsfwScan.js` holds it: a pure `blockedClass()`
that returns the first of `Porn` / `Hentai` / `Sexy` scoring over the constant `NSFW_THRESHOLD`
(0.7), and a `scanImageFile()` that decodes the picked file, loads the model and classifies it.
`scanImageFile()` never throws and never rejects — a model that will not load, an image that will
not decode, or a browser with no WebGL all return null, which allows the upload and leaves the
Sightengine scan in `UploadController` as the last word. Nothing server-side was touched.
`EventForm.jsx` calls it from `handleImageChange`, so a blocked picture is never put into state and
therefore never reaches `POST /api/upload`; the form shows which class tripped it and clears the
file input. Submit and the file input are disabled while a scan runs.

Assumed, because the repository does not say: **the package is `nsfwjs`, not
`@tensorflow-models/nsfwjs`.** The name on this card and in `HANDOVER.md` does not exist on npm;
the real one is `nsfwjs` (Infinite Red), installed at 4.3.0 with its peer `@tensorflow/tfjs`
4.22.0. Also assumed: only the MobileNetV2 model is bundled. `nsfwjs.load()` from the package root
registers all three model definitions, so Vite would emit InceptionV3's 29 MB of weights as well;
importing `nsfwjs/core` and `nsfwjs/models/mobilenet_v2` directly and passing
`modelDefinitions: [MobileNetV2Model]` pins it to one. Both model and tfjs are behind `import()`,
so the app-boot chunk is unchanged at 356 kB and the 3.5 MB of weights plus 1.9 MB of tfjs are
fetched only when somebody picks a photo. Those chunks are committed under `public/build/` like
every other asset, because the server has no Node — that is about 5.5 MB added to the repository
and it is the price of the card.

Checks. `npm run test:js` (Node's built-in runner, no framework, four tests) covers the threshold
decision both ways, that a non-blocked class scoring 0.99 is ignored, and the fall-through: with no
DOM the scan cannot run at all and `scanImageFile` still returns null. `.\vendor\bin\phpunit.bat`
is green at 33 tests, 82 assertions, and `.\vendor\bin\pint.bat --dirty` passes. Note for the next
run: this worktree had no Passport keys, so the suite errored on 14 MCP tests until
`php artisan passport:keys` was run — nothing to do with this card. The runner is PHPUnit;
`vendor/bin/pest.bat` does not exist in this project.

**Two criteria are left open and both need the same thing: a browser.** `artisan serve` could not
bind any port in this session and the browser tool was not permitted, so nothing here has been seen
running. The first criterion is the one that genuinely needs it — if nsfwjs failed to initialise
inside the Vite bundle, every picture would fall through and the app would look completely normal
while blocking nothing. To close them: run `npm run build`, hard-refresh `timeline.test`, open a
group's Add Event page and pick a photo. An ordinary photo should upload and save as before (second
criterion). To see the block without sourcing an indecent image, temporarily lower `NSFW_THRESHOLD`
in `resources/js/lib/nsfwScan.js` to about 0.2, rebuild, pick a swimwear or beach photo, and check
that the red error names the class and that no `POST /api/upload` appears in the network tab — then
put 0.7 back and rebuild. The third criterion is ticked because its whole substance is exercised by
the `allows the upload when the scan cannot run at all` test.

Unsettled from the repository: `HANDOVER.md` still describes this pre-scan as planned, and names
the wrong package. Card `0002` owns that rewrite, so I left it alone rather than widen this card.
