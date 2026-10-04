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
- [ ] WHEN a model load stalls past its timeout, THE APP SHALL drop the cached load, so the next photo
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
