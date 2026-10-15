# Handover calls the client-side pre-scan unreviewed after card 0001 finished

## Why
**`HANDOVER.md` says the browser pre-scan "has not passed review", and card `0001` is in
`docs/board/done/`.** Section "Not settled: the client-side pre-scan" opens with that sentence, and
"Work in flight" counts `0001` as one of two open things about content moderation. `0001` moved
from `ai-review/` to `done/` in commit `999dc3c`.

**What it costs.** A new session reads the handover first. It will treat finished, reviewed work as
unsettled, and may spend time re-reviewing it.

**How it came to be this way.** Card `0007` fixed only the lane link in that section and left the
status prose alone, because changing it was outside that card's scope.

## Links

**Relates to**
- `0001` - the card whose status the prose gets wrong.
- `0007` - fixed the lane link in the same section and fenced this out.

## Not this card
Any change to card `0001`, and any other section of `HANDOVER.md`.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN `HANDOVER.md` describes the client-side pre-scan or card `0001`, IT SHALL NOT call it
      unreviewed or open while the card is in `done/`. proves: none - about prose
<!-- AC:END -->

## Plan
Read `docs/board/done/0001-client-side-nsfw-pre-scan.md` for what was accepted. Rewrite the
"Not settled: the client-side pre-scan" heading and paragraph, and the last paragraph of
"Work in flight", in `HANDOVER.md` at the repository root, to match.

## Comments

**2026-10-15** RESULT: done
TESTS: +0 new, all green (criterion 1 is proves: none; phpunit 67 tests green, pint --test pass)
TOUCHED: HANDOVER.md
OUT-OF-SCOPE: none

Rewrote the two places the card's Plan names, nothing else. The heading "Not settled: the client-side pre-scan" is now "Client-side pre-scan", and its paragraph says card 0001 is built, reviewed and in done/, then states what was accepted: nsfwjs MobileNetV2 in the browser, 0.7 constant on Porn/Hentai/Sexy, blocks before POST /api/upload, server scan still runs, admin switch skips it, 15 s model and 5 s settings timeouts fall through. Figures checked against resources/js/lib/nsfwScan.js. The last paragraph of "Work in flight" now names one open moderation card, 0003 (still in human-review/), and says 0001 is finished. No other file links to the old heading anchor. The card's prompt pointed at docs/HANDOVER.md; that file does not exist, the handover is at the repository root as the card's Plan says. pest.bat does not exist here; the runner is phpunit.bat.
