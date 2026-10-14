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
