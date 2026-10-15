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

### 2026-10-15 review (v20261015030416-47a8)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 26s, run by this job rather than reported by the card.

**acceptance: sound**

The card has one rule. `HANDOVER.md` must not call the pre-scan or card `0001` "unreviewed" or "open" while the card is in `done/`.

**What I checked:**
- Card `0001` is in `docs/board/done/`. I found it at `docs/board/done/0001-client-side-nsfw-pre-scan.md`.
- `HANDOVER.md` names `0001` or the pre-scan in only three places. I searched for `0001`, `pre-scan`, `not passed` and `nsfwScan`.
- In the section "Client-side pre-scan", the heading no longer says "Not settled".
- That section's paragraph says "Built and reviewed" and links `done/`.
- The last paragraph of "Work in flight" says that only `0003` is open, and that `0001` "is finished".
- No text says "has not passed review". No text counts `0001` as open.

The rule is met. The suite is green.

The diff also holds code changes for card `0014`. Those come from a different commit and a different card, so they are not part of this criterion.

VERDICT: sound

**scope: sound**

**Scope check for card 0016: no problems found**

- **The card's own work stayed in scope.** The card's own work is in commit `9b5a634`. That commit changes one file, `HANDOVER.md`. It changes the two places the card's Plan names:
  - the "Client-side pre-scan" heading and its paragraph;
  - the last paragraph of "Work in flight".
- **The other changes in the diff belong to card 0014.** They are in commit `7340581`. That card changed `EventController::show`, `GetTimelineEventTool::handle`, `Event::isVisibleTo`, two test files and one `HANDOVER.md` line about `visibleIn`. The rest are board file moves. I do not count them against 0016.
- **The fence holds.** Card `0001` did not change. No other section of `HANDOVER.md` changed in `9b5a634`.
- **Nothing is half done.** No other `.md` file outside `docs/board/` still says "Not settled" or "has not passed review". Changing the heading breaks no link.

Criterion #1 is met.

VERDICT: sound

**breakage: sound**

I tried to break this card's change. I could not.

**What I checked:**
- `HANDOVER.md` names card `0001` or the pre-scan in two places only:
  - The section "Client-side pre-scan". It says the card is "built and reviewed" and in `done/`.
  - The last paragraph of "Work in flight". It says `0001` "is finished".
- Neither place calls the pre-scan unreviewed or open.
- The words "Not settled", "not passed review" and "unreviewed" are gone from the file.
- Card `0001` really is in `docs/board/done/`, so the new `done/` link is correct.
- The builder says no other file links to the old heading anchor.

**Out of scope:** the other files in the diff are from card `0014`. That card has its own review, so I did not judge them here.

No criterion is disproved.

VERDICT: sound

