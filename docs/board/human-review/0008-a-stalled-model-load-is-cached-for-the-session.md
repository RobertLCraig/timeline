# A stalled model load is cached for the rest of the session

## Why
**After one stalled model fetch, every later photo pick waits the full 15 seconds again.**
The photo picker on the Add Event page is locked for that wait each time. The pre-scan then gives up
and the server scan decides, so nothing is blocked wrongly, but the user waits for nothing.

**What it costs.** A user on a bad connection waits 15 s on every photo for the rest of the
session, even after the network recovers.

**How it came to be that way.** `loadModel()` in `resources/js/lib/nsfwScan.js` clears its cached
`modelPromise` only when the load *rejects*. A stalled load never rejects. `withTimeout()` in
`scanImageFile()` rejects its own race, not the cached promise, so the stalled promise stays cached.
Card `0001` named this as "worth fixing on the way, not a criterion", and its build left it alone.

## Links

**Relates to**
- `0001` - built the pre-scan and the 15 s model-load timeout this fault lives in.

## Not this card
The threshold, the admin switch, the settings fetch, or anything server-side.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN a model load stalls past its timeout, THE APP SHALL drop the cached load, so the next photo
      pick starts a fresh load. proves: `a stalled model load is not reused by the next pick`
<!-- AC:END -->

## Tasks
- [ ] Clear `modelPromise` when the timeout fires, not only when the load rejects
- [ ] Test it with Node's runner (`npm run test:js`)

## Plan
Stand in the timeline repository. `scanImageFile()` takes an injectable `load` option, but the cache
lives inside the default `loadModel()`, so the test needs a way to give `loadModel()` a stalling
import. One way: move the timeout inside `loadModel()` (wrap the import chain in `withTimeout()`),
so the existing `.catch(() => { modelPromise = null; })` also fires on a stall. Then call the loader
twice in the test and assert it started two loads.

## Comments

**2026-10-05** RESULT: done
TESTS: +1 new, all green
TOUCHED: resources/js/lib/nsfwScan.js
resources/js/lib/nsfwScan.test.js
OUT-OF-SCOPE: none

Followed the Plan: the 15 s timeout now wraps the import inside loadModel(), so the existing .catch clears the cached modelPromise on a stall as well as a rejection. loadModel is exported with an injectable importModel and timeoutMs so the test can feed it a stalling import. Test `a stalled model load is not reused by the next pick` was watched red first (1 !== 2: the second pick reused the stalled load), then green. scanImageFile keeps its own withTimeout for injected loaders. npm run test:js 9/9, php artisan test 37/37, pint pass. vendor\bin\pest.bat does not exist in this tree, so the PHP suite ran via artisan test. public/build was not rebuilt and no browser check was done: Herd serves C:\Dev\timeline, so this still needs npm run build and a browser check after merge.

**2026-10-05** RESULT: done
TESTS: +0 new this take (the +1 from the first take, commit 2cef4ec), all green
TOUCHED: none this take (first take: resources/js/lib/nsfwScan.js, resources/js/lib/nsfwScan.test.js)
OUT-OF-SCOPE: none

Re-take of a card already built. I re-checked the first take's work instead of building it again. To watch it red, I removed withTimeout() from loadModel() for a moment: `a stalled model load is not reused by the next pick` failed with 1 !== 2, which is the fault the criterion describes. With the fix back: npm run test:js 9/9, php artisan test 37/37, pint --test pass. vendor\bin\pest.bat is not in this tree, so the PHP suite ran through artisan test. The handover is at HANDOVER.md in the repository root, not docs/HANDOVER.md as the prompt says. Nothing new to commit. Still needs npm run build and a browser check after merge, because Herd serves C:\Dev\timeline.

**2026-10-05** The loop moved this card from in-progress/ to human-review/. 2 takes in a row ended with it still in in-progress/, and the last one said: `staying in in-progress: 1 of 1 named test(s) never ran`. What this card is waiting for is not another session. bin/work-card.ps1 counts those takes out of storage/logs/work-card.log, and will start it again as soon as a person has moved it back to todo/.
