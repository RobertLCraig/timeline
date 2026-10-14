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
- [ ] WHEN `HANDOVER.md` describes the client-side pre-scan or card `0001`, IT SHALL NOT call it
      unreviewed or open while the card is in `done/`. proves: none - about prose
<!-- AC:END -->

## Plan
Read `docs/board/done/0001-client-side-nsfw-pre-scan.md` for what was accepted. Rewrite the
"Not settled: the client-side pre-scan" heading and paragraph, and the last paragraph of
"Work in flight", in `HANDOVER.md` at the repository root, to match.
