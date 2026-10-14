# Handover sends the reader to the wrong lane for card 0001

## Why
**`HANDOVER.md` says card `0001` is in `docs/board/human-review/`, and it is not.** Section "Not
settled: the client-side pre-scan" links that folder for it. The card moved back to `todo/` on
2026-09-29 (commit `f42ea1f`), so a reader who follows the link finds no `0001` there.

**What it costs.** The handover is the page a new session reads first. A link to the wrong folder
sends it searching, and it makes the reader doubt the rest of the section.

**How it came to be this way.** The handover names a lane folder, and lanes change every time a card
moves. Nothing updates the handover when that happens.

## Links

**Relates to**
- `0001` - the card the handover points at; its lane is what the link gets wrong.

## Not this card
Any other change to `HANDOVER.md`, and any change to card `0001`.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN `HANDOVER.md` names where card `0001` is, IT SHALL NOT name a lane folder the card is
      not in. proves: none - about prose
<!-- AC:END -->

## Plan
Name the card by number without a lane folder, or point at `docs/board/` as a whole, so the link
stays true when the card moves again.

## Comments

**2026-10-14** RESULT: done
TESTS: +0 new, all green (64 passed via php artisan test; the criterion is proves: none)
TOUCHED: HANDOVER.md
TOUCHED: docs/board/todo/0016-handover-calls-the-pre-scan-unreviewed-after-0001-finished.md
OUT-OF-SCOPE: 0016
The handover is at the repo root (HANDOVER.md), not docs/HANDOVER.md. Line 332 now links card 0001 to docs/board/ as a whole, so the link stays true when the card moves. Card 0001 is now in done/ (commit 999dc3c), not in todo/ as this card's Why says. The same paragraph still says the pre-scan 'has not passed review', and 'Work in flight' counts 0001 as open. That is outside this card's fence, so it is raised as 0016. Line 340 links human-review/ as the lane for what waits on a person. That describes the lane, not where 0001 is, so it is left alone. Note for the loop: vendor/bin has no pest.bat. This project runs PHPUnit through php artisan test. pint --test passes. No browser check is needed: the change is docs only.

### 2026-10-14 review (v20261014212830-1886)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 30s, run by this job rather than reported by the card.

**acceptance: sound**

I checked the one rule on this card.

**Rule #1:** When `HANDOVER.md` says where card `0001` is, it must not name a lane folder that the card is not in.

**What I found:**
- `HANDOVER.md` line 332 now says card `0001` is "on the [board](docs/board/)". This is the whole board, not one lane folder. It stays true when the card moves.
- Line 340 links `docs/board/human-review/`. That line tells you where cards go when they wait for a person. It does not say where `0001` is, so the rule does not apply to it.
- Line 347 names card `0001` but gives no folder.

No place in `HANDOVER.md` puts `0001` in the wrong lane folder. The rule is met. The test suite passed.

A separate problem is still open. The handover still says the pre-scan "has not passed review", but card `0001` is in `done/` now. This card says not to fix that. It is already logged as card `0016`.

VERDICT: sound

**scope: sound**

I could not find a scope problem.

**What the builder changed for this card.** Commit `56c7e2c` changes two files:
- `HANDOVER.md`: one line in the "Not settled: the client-side pre-scan" section. That line used to link `docs/board/human-review/`. It now links `docs/board/` as a whole. This is the card's own Plan.
- One new card, `docs/board/todo/0016-...`. The builder found a problem outside this card's fence and filed it as a card. It did not fix it here, so this does not break the fence.

**The other 86 files in the diff are not from this card.** They come from earlier commits, such as `c5f2499` (comments) and `3213346` (photos). Those commits belong to other cards, so I do not count them against 0001's fix.

**I checked the criterion.** `HANDOVER.md` names card 0001 in two places, lines 332 and 347. Neither place names a lane folder now. Line 340 does still link `human-review/`, but it describes that lane in general. It does not say where 0001 is.

**Nothing is left half done.** Card 0001 was not changed.

VERDICT: sound

**breakage: sound**

I tried to break this card's fix, and I could not.

**What I checked:**
- `HANDOVER.md`, section "Not settled: the client-side pre-scan": card `0001` now links to `docs/board/`. That is the whole board, not one lane. Card `0001` is in `docs/board/done/`, so the link stays true when the card moves.
- `HANDOVER.md` has no other place that names a lane for `0001`. The second mention, in the "Work in flight" text, names only the card number.
- The `human-review/` link in the same file describes what that lane is for. It does not say where `0001` is.

**What is still wrong, but outside this card:**
- The same paragraph says the pre-scan "has not passed review". Card `0001` is in `done/`, so that text is now false. The builder raised it as card `0016`, which is correct.
- In the "Event comments" section, the old line about category defaults was added to the end of the new comments paragraph. This came from card 0011's change, not this card.

No criterion is disproved.

VERDICT: sound

