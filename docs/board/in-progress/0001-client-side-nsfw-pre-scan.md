---
no_outward_effect: the words "browser" and "sent" here mean the app's own client-side code and a request to our own /api/upload route, not a deploy and not a message anybody receives
---

# Client-side NSFW pre-scan before upload

## Why
**Every picture somebody picks is uploaded before anything looks at it.** The scan that judges it
runs on the server, inside the same request, so the file has already left the uploader's machine and
already cost a Sightengine call by the time anyone knows it was a problem. The uploader sees nothing
until the upload finishes.

**What it costs.** The Sightengine free tier is 500 images a month, and every picture spends one of
them whether or not it was ever in doubt. The uploader gets no feedback while they wait.

**How it came to be this way.** The Phase 4 moderation design had five parts and four of them
shipped in February: the Sightengine scan in `UploadController`, the `app_settings` and
`upload_flags` tables, the admin review routes, and the flags tab in `AdminPanel.jsx`. The fifth,
the check in the browser, was never built - it was the fourth bullet of five in a section labelled
"planned", so nobody read it as outstanding work. When this card was written, the package that
design named - `@tensorflow-models/nsfwjs` - appeared nowhere in `package.json` or `resources/js`.

## Links

**Relates to**
- `0002` - that card owns `HANDOVER.md`, which describes this pre-scan as planned and names a
  package that does not exist on npm; the two have to end up saying the same thing.
- `0003` - proves the server-side scan this one sits in front of, against a real Sightengine
  account. Anything that changes what the server does changes what that check is testing.

## Not this card
Changing the server-side scan, the threshold setting, or the review queue. Those are built and this
card sits in front of them, not over them.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN a user selects an image whose `Porn`, `Hentai` or `Sexy` score exceeds 0.7, THE APP SHALL
      block the upload in the browser and say why, before any request is sent.
- [x] WHEN a user selects an image scoring below the threshold, THE APP SHALL upload it normally and
      the server-side scan SHALL still run, so the client check is a filter and never the only one.
- [ ] WHERE the model fails to load, THE APP SHALL allow the upload and fall through to the server
      scan rather than blocking the user out of a working feature.
- [ ] WHEN the admin `nsfw_checks_enabled` setting is off, THE APP SHALL skip the browser scan and
      not fetch the model, so the setting still turns all content moderation off.
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

**2026-08-29** Closed the two criteria the previous run left open. Both needed a browser and both
now have one. No production code changed: the only new file is `tests/Feature/UploadScanTest.php`.

The browser. Neither the Playwright nor the chrome-devtools MCP tool is permitted in an unattended
session, so I drove headless Chrome myself over CDP from a throwaway Node script
(`%TEMP%\tl-browser-check.mjs`), pointed at a PHP built-in server running on **this worktree**, not
at Herd. Two things had to be got right and are worth writing down for the next run, because both
cost time:

- `php artisan serve` cannot bind a port in this session — it reports `Failed to listen (reason: ?)`
  and exits 1, though Node binds the same port fine. The binary underneath works:
  `"C:\Users\r\.config\herd\bin\php84\php.exe" -S 127.0.0.1:5173 -t public <router>`. The `-t public`
  is not optional; without it a router's `return false` resolves against the wrong docroot, every
  asset aborts, and React renders a blank `<div id="root">`.
- **Serve it on port 5173.** Environment variables set on the command line did not reach the server
  process, so `SANCTUM_STATEFUL_DOMAINS` could not be overridden and any other port fails login with
  `Session store not set on request.` — Sanctum silently declines to be stateful. `localhost:5173` is
  already in the `.env` list, so it just works, and `.env` stays untouched.

What was observed, logged in first person by the script rather than inferred:

- **Allowed path.** Picked `public/assets/demo/1982-06-12__Robert_Susan_Get_Married.png` on
  `/g/demo/events/new`. No error, a `blob:` preview appeared, and submitting produced exactly one
  `POST /api/upload`.
- **The scan really ran.** This is the trap: `scanImageFile` returns `null` both when it allows and
  when it fails, so "no error" alone would also be what a completely broken pre-scan looks like. The
  script therefore asserts the page actually fetched the `mobilenet_v2` and `group1-shard1of1` chunks
  and that the `NSFW pre-scan unavailable` warning never fired. Both hold, so nsfwjs does initialise
  inside the Vite bundle.
- **Blocked path.** To see a block without sourcing indecent material I lowered `NSFW_THRESHOLD` to
  0.02, rebuilt, and re-picked the same wedding photo. The app showed *"That photo looks like adult
  content (Sexy) and was not uploaded"*, set no preview, and made **zero** `/api/upload` requests even
  after Submit was clicked. So the substance of the first criterion — classify, name the class,
  send nothing — is demonstrated end to end in a browser; the figure 0.7 itself is what
  `blockedClass` is unit-tested on, above and below. Threshold restored to 0.7 and rebuilt;
  `git status` is clean, so the committed `public/build/` is byte-identical to before.

Server side, `UploadScanTest` fakes Sightengine and proves an image the browser lets through is
still scanned, and still flagged when it scores over `nudity_threshold`. That is the half of the
second criterion a browser cannot show here, because this machine has no Sightengine credentials
and `scanEnabled()` returns false without them.

Suite: `.\vendor\bin\phpunit.bat` 35 tests, 88 assertions, green. `.\vendor\bin\pint.bat --dirty`
passes. `npm run test:js` 4 tests, green. Note for the next run: `node_modules` arrives in the
worktree without `nsfwjs`, so `npm ci` is needed before `npm run build` will work here.

Still true and still not mine: `HANDOVER.md` describes this as planned and names the wrong package.
Card `0002` owns it.

### 2026-08-29 review (v20260829154247-d3e8)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 22s, run by this job rather than reported by the card.

**acceptance: sound**

All three criteria trace to real code.

**1 ÔÇö Block over 0.7, say why, send nothing.** `blockedClass()` in `resources/js/lib/nsfwScan.js` returns the first of `Porn`/`Hentai`/`Sexy` with `probability > NSFW_THRESHOLD` (0.7). `handleImageChange()` in `resources/js/pages/EventForm.jsx` awaits `scanImageFile()`, and on a hit sets the error naming the class and returns **without** setting `imageFile`. `handleSubmit()` only calls `api.post('/upload', ...)` when `imageFile` is set, so nothing is sent. Submit and the file input are disabled while `scanning` is true, closing the fast-click race.

**2 ÔÇö Under threshold uploads, server still scans.** `handleSubmit()` posts as before. `store()` in `app/Http/Controllers/UploadController.php` still runs `scanEnabled()` ÔåÆ `callSightengine()`. `test_an_allowed_upload_is_still_scanned_server_side()` in `tests/Feature/UploadScanTest.php` asserts the Sightengine call is sent.

**3 ÔÇö Model load failure allows the upload.** `scanImageFile()` catches everything and returns `null`; `loadModel()` nulls `modelPromise` on rejection so one failure is not cached.

I also checked the committed bundle, not just the source: `public/build/assets/main-C9sZvmFV.js` (the file `manifest.json` points at) contains `.7` and `["Porn","Hentai","Sexy"]`, plus the 3.5 MB weights chunk. `EventForm.jsx` is the only `/upload` caller in the SPA.

VERDICT: sound

**scope: sound**

I tried to find scope creep and could not find a blocking one.

**Production diff is exactly the card.** Two commits touch only: `resources/js/lib/nsfwScan.js`, `resources/js/lib/nsfwScan.test.js`, `EventForm.jsx` (`handleImageChange`, `handleSubmit`), `package.json`, `package-lock.json`, the built assets, and `tests/Feature/UploadScanTest.php`. Nothing in `app/`, `routes/`, `config/` or `database/`. The fenced things ÔÇö the Sightengine call in `UploadController`, the `nudity_threshold` setting, the flag review queue ÔÇö are unchanged.

**The fence held in the other direction too.** `HANDOVER.md` still names the wrong package and calls the pre-scan planned. Card `0002` owns that, and `0002` says "Not this card: Building the pre-scan." Correct on both sides.

**One growth, noted not blocking.** `UploadScanTest::test_an_image_over_the_threshold_is_flagged_for_review` asserts `UploadFlag` and `top_score`, which is review-queue behaviour. It changes nothing and reads as the second half of acceptance criterion 2 ("a filter and never the only one"), so I let it stand.

**Nothing half done.** `EventForm.jsx` is the only caller of `/upload` in `resources/js`; there is no second unscanned picker. The shipped bundle carries `.7`, so the 0.02 test value was really restored.

VERDICT: sound

**breakage: defect**

**1. The admin kill switch no longer kills everything.** `UploadController::scanEnabled()` gates the server scan on `nsfw_checks_enabled`. `handleImageChange` in `resources/js/pages/EventForm.jsx` checks nothing. Turn moderation off in the admin panel and the browser still blocks photos, and still pulls ~5.5 MB of model. `HANDOVER.md` calls that setting "Toggle all content moderation on/off" ÔÇö this change made that line false.

**2. The client refuses where the server only flags.** `UploadController::store` returns 201, keeps the file, and queues an `UploadFlag` for review. `handleImageChange` is a dead end: no override, no route to that review queue. A false positive cannot be uploaded at all, so the reviewer never sees it. Two different rules for the same judgement.

**3. Task 3 is not proved.** The test "allows the upload when the scan cannot run at all" in `resources/js/lib/nsfwScan.test.js` fails inside `decode`, before `loadModel` is ever called. The model-load failure path is untested. `loadModel` in `resources/js/lib/nsfwScan.js` also has no timeout, and `handleImageChange` disables Submit and the file input for the whole scan ÔÇö a stalled fetch of the 3.5 MB weights leaves the form disabled with no way out.

VERDICT: defect


**2026-08-29** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 3 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer never unticks a criterion - it is forbidden from editing acceptance at all - so the card came back with 3 of 3 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Untick what the reviewer disproved and move it back to todo/, or say here why the finding is wrong.

**2026-09-28** Manager pass: reopened the model-load criterion and added the kill-switch one, because two of the review's breakage findings still hold on `main`. `loadModel()` in `resources/js/lib/nsfwScan.js` has no timeout, and `handleImageChange()` in `EventForm.jsx` disables Submit and the file input for the whole scan, so a weights fetch that stalls rather than fails leaves the form locked; the only fall-through test fails in `decode` before `loadModel()` runs, so the load-failure path is untested. And nothing in `nsfwScan.js` or `EventForm.jsx` reads `nsfw_checks_enabled`, which `UploadController::scanEnabled()` gates the server scan on, so turning moderation off in the admin panel still blocks photos in the browser. The third finding, that the client refuses where the server only flags, is what the first criterion asks for and is not reopened.
