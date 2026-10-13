---
no_outward_effect: "browser" is a local Playwright look at timeline.test; nothing is deployed
---
# Make the site installable on a phone

## Why
**The timeline cannot be put on a phone's home screen as an app.** It works in a phone browser,
but it opens inside the browser with its address bar, and there is no icon to tap. The site has no
web app manifest and no service worker (`public/` holds neither, and `resources/views/welcome.blade.php`
links neither), so no phone offers "Install" or "Add to Home Screen" as an app.

**What it costs.** Family members reach the timeline through a bookmark or a typed address, so it
is used less than an app on the home screen would be.

**How it came to be this way.** `0013` asked what "the phone app" should be. Rob chose **D** on
2026-10-11: make the website installable, rather than a React Native app (C) or nothing (E).

## Links

**Relates to**
- `0013` - the decision this card builds. That card now holds only the mail password.

## Not this card
- Push notifications. They do not work on every phone, and nothing sends one yet.
- Offline use of the timeline's data. The service worker exists so the site can be installed; it
  does not need to cache API responses.
- A React Native or store app.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN the home page is served, IT SHALL link a web app manifest whose `name`, `short_name`, `start_url`, `display: standalone`, theme colour and 192px and 512px icons are all set. proves: `test_the_home_page_links_a_complete_web_app_manifest`
- [x] #2 WHEN the site loads, IT SHALL register a service worker served from the site root, so its scope covers every page. proves: `test_a_service_worker_is_served_from_the_root`
- [ ] #3 WHEN the site is opened in Chromium at a phone width, THE BROWSER SHALL report it installable (no manifest errors in the Application panel, and the `beforeinstallprompt` event fires). proves: manual - Playwright on `timeline.test` at 390px, recorded on this card
<!-- AC:END -->

## Tasks
- [ ] Manifest and icons in `public/`, linked from `resources/views/welcome.blade.php`
- [ ] A minimal service worker at `public/sw.js`, registered from `resources/js/main.jsx`
- [ ] Tests named above
- [ ] `npm run build` and commit `public/build/`

## Plan
Stand in the timeline repository on the card's branch. Read `CLAUDE.md` first (stack, Herd serves
`timeline.test` from `public/build`, assets are committed because the server has no Node).

1. **No new dependency.** A static `public/manifest.webmanifest` and a hand-written `public/sw.js`
   are enough; `vite-plugin-pwa` is not needed for an install-only worker.
2. **The worker must sit at the root.** A worker under `/build/` can only control `/build/`. Serve
   `sw.js` from `public/` so its scope is `/`.
3. **A fetch handler is required for Chromium to treat the site as installable.** A pass-through
   `fetch` listener is enough. Never cache `/api/*` or `/mcp`: those answers are per user.
4. **Icons.** Make 192px and 512px PNGs from the site's own mark (the ⏳ logo in `Navbar`). Put
   them in `public/icons/`.
5. **Secure context.** A service worker only registers on HTTPS or `localhost`. `timeline.test` is
   plain HTTP under Herd, so either run `herd secure timeline` for the browser check or check #3 on
   `http://localhost`. Say which on the card.
6. Run `composer test`; it must be green.

## Comments
**2026-10-11** Raised from `0013`, where Rob chose D (make the site installable) on 2026-10-11.

**2026-10-13** RESULT: partial
TESTS: +2 new, all green (64 tests, full suite via vendor\bin\phpunit.bat; pint --test passes)
TOUCHED: tests/Feature/InstallableSiteTest.php
public/manifest.webmanifest
public/sw.js
public/icons/icon-192.png
public/icons/icon-512.png
resources/views/app.blade.php
resources/js/main.jsx
public/build/manifest.json
public/build/assets/main-CQg0imu7.js (replaces main-D-l_SlW9.js)
public/build/assets/mobilenet_v2-B-KXMsG5.js (replaces mobilenet_v2-Cak5fkzL.js)
OUT-OF-SCOPE: none

#1 and #2 met. Both tests were watched red first: #1 failed on the missing <link rel="manifest">, #2 on the missing public/sw.js.

#3 NOT met. It needs a person: Playwright at 390px, then record on the card that the Application panel shows no manifest errors and beforeinstallprompt fires. Herd serves C:\Dev\timeline, not this worktree, and this sandbox refused to open a port for `php artisan serve`, so no browser saw this build. After the merge, either run `herd secure timeline` and check https://timeline.test, or check on http://localhost (plain-HTTP timeline.test is not a secure context, so the worker will not register there).

What I changed from the Plan: the manifest is linked from resources/views/app.blade.php, not welcome.blade.php. web.php serves `app` for every page; welcome.blade.php is the unused Laravel stub, so a link there would never reach a browser. Theme colour is --color-primary #6366f1; background is --bg-primary #0f0f23. Icons are the Segoe UI Emoji hourglass (the Navbar's logo) on #0f0f23, kept in the middle 55% so a round mask does not clip it.

The card's instructions say pest.bat; this repo has no Pest, only PHPUnit, so I ran vendor\bin\phpunit.bat.

Environment note: `npm run build` failed until `npm ci`, because nsfwjs (in package.json and the lockfile) was missing from node_modules here and in C:\Dev\timeline. No package file changed. The mobilenet_v2 chunk got a new hash from that rebuild.
