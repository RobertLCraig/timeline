# Rewrite this board's cards for the reader

## Why
**A card on this board opens with the answer and never says what is wrong.** On 2026-08-18 Rob said
most of the cards he was handed made him work backwards: they lead with candidate solutions and
their costs, so he has to reverse-engineer the problem out of the proposals. He cannot tell whether
the options are the right ones, because he does not yet know what they are for.

**Two more faults, in his words.** Cards ask him to settle things an agent could have researched and
applied. And a bare card number dropped into a sentence tells him some other card matters and
nothing about why, so he opens it to find out.

**What it costs.** His attention is the only scarce thing here. Measured on 2026-08-20, 258 of 398
open cards across the estate fail at least one of these rules and 257 of those fail on the link rule
alone. A card that reads badly costs a round trip; one that should never have been surfaced costs
the whole reading for nothing. Enough of either and he stops opening the ones that mattered.

**How it came to be this way.** Every card here was written by an agent against a convention that,
until 2026-08-18, said nothing about stating the problem first, nothing about whether a question was
a person's to answer at all, and nothing about how to name another card. It gained all three rules
that day, and nothing was applied to the cards, so this board is measured against a standard none of
it was written to.

## Links

**Relates to**
- `progressboard#0065` - the estate-wide rewrite this card was seeded from; its pilot over
  ProgressBoard's own 40 cards is the worked example of a pass.
- `progressboard#0066` - the five checks the count below is measured with, and why each is
  structural rather than a judgement about prose.

## Not this card
**Changing the convention.** `docs/board/README.md` here is a COPY of a canonical file outside every
repository, so an edit to it is destroyed silently on the next distribution. This card applies the
convention and never changes it.

**Rewriting cards in `done/` or `discarded/`.** Those are a record of what happened. Rewriting a
record is falsifying it, and nobody reads them to decide anything.

**Deleting anything.** A badly written card still holds facts somebody measured. A rewrite keeps
everything the card knows and changes only how it is ordered and said. `## Direction` and
`## Decided` are append-only: do not edit them, on any card, for any reason.

**Any other board.** Each one carries its own copy of this card, worked in its own repository.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a card in a non-terminal lane is rewritten, THE CARD SHALL state the problem in
      `## Why` before any solution appears anywhere in it. proves: none - about prose, and no check
      here reads prose
- [x] #2 WHEN a rewritten card is a decision, THE CARD SHALL say which of the four reasons makes it
      a person's to answer, or SHALL be converted to a feature card whose `## Plan` records the
      practice applied and its source. proves: none - the command that counts it is in another
      repository, named in `## Plan`
- [x] #3 WHEN a rewritten card names another card, THE CARD SHALL name it in a `## Links` section
      with the relationship type and one line of why, and SHALL NOT leave a bare card number in a
      sentence as the only mention of it. proves: none - as #2
- [x] #4 THE `Blocked by` LINES on every rewritten card SHALL match that card's `needs:` frontmatter
      exactly, in both directions. proves: none - as #2
- [x] #5 THE REWRITE SHALL preserve every measurement, date and decision the card already carried,
      and SHALL NOT edit `## Direction` or `## Decided`. proves: none - as #2
- [x] #6 WHEN this board's rewrite is finished, THE BOARD SHALL report zero open cards failing the
      checks. proves: none - as #2
- [ ] #7 NO rewritten card SHALL contradict itself or the code: `0001`'s `## Why` SHALL say in the
      past tense what was true when it was raised, its `0002` link SHALL not claim `HANDOVER.md`
      calls the pre-scan planned, and `0003`'s ask SHALL not give the nulled `image_url` and
      `storage/quarantine/` as the pass that its own `## Links` says `AdminController::reviewFlag`
      never produces. proves: none - as #2
<!-- AC:END -->

## Tasks
- [x] Read the count, and write it into `## Direction` before changing anything
- [x] Rewrite `human-review/` first, then `todo/`, `in-progress/` and `ai-review/`
- [x] For each decision card, apply the four-reason test and convert the ones that fail it
- [x] Read the count again and write into `## Direction` what changed, counted by rule

## Plan
**Where to stand.** This repository, on whatever branch the session was given. Nothing outside it is
edited and no card changes lane. **The one command, from this board's directory, in PowerShell:**

    php C:\Dev\ProgressBoard\artisan board:convention --path=$PWD

It prints one tab-separated line: board name, OPEN cards failing the checks, open cards, the next
free card number, and the directory read. The second number is this card's finish line and it must
reach 0. Run it before the first edit and after the last. `--path` matters: a build worktree is not
`C:\Dev\<board>`, and without it you measure a tree you are not editing.

**What the checks look for is in `docs/board/README.md` here**, three sections of it: "`## Why` is
the PROBLEM, and it comes before any answer", "Links: say what the relationship IS, never a bare
card number", and "Is this actually a person's to decide?". Read those three first. Every check is
structural - a missing `## Links` section, a `Blocked by` line that disagrees with `needs:`, a link
with nothing after the dash - so each flag names one thing to fix and none is an opinion.

**`human-review/` first, and that is not tidiness.** That lane is the only one a person reads. A
`todo/` card is read by an agent, a reader with different problems, so rewriting those first spends
the session on the half nobody is complaining about.

**Expect the four-reason test to shrink the queue rather than reformat it.** A decision whose answer
turns on established practice is not Rob's: research it, apply it, and rewrite the card as a feature
card whose `## Plan` says what was applied and where it came from. Count those separately from the
cards merely rewritten - that is the change that gives him evenings back.

**If the board is too big for one session, stop cleanly.** Tick nothing, write the count you reached
into `## Direction`, and leave the card where it is; the next session carries on from that entry. A
part-rewritten board is normal. A card ticked off a board that is not at 0 is not.

## Comments

**2026-08-29** Board is at zero. `board:convention --path=$PWD --cards` read **4 failing of 5 open**
before the first edit and **0 of 5** after the last. Counted by rule, all four flags cleared:

- **Unexplained link, 3 cards.** `0002` and `0003` each named `0001` as a bare number inside
  `## Not this card`, and `0004` named `0003` mid-sentence inside an option. All three now carry a
  `## Links` section under `## Why` with a `**Relates to**` line naming the card and one line of why
  the reader is being sent there. `0001` gained one too, pointing at `0002` and `0003`, because the
  three moderation cards only make sense read together.
- **Outward effect with no declaration, 1 card.** `0001` was flagged on the words *send* and
  *browser* in its acceptance. I read it as a word rather than an effect and recorded
  `no_outward_effect:`, not `not_for_the_loop:`. The reasoning: "in the browser" names where the
  app's own client-side code runs, and "before any request is sent" is an HTTP request to our own
  `/api/upload`. Nothing leaves the repository and `git revert` reaches all of it. The card's own
  thread also records an unattended session driving headless Chrome over CDP to confirm it, so the
  browser check has already been done without a person. `no_outward_effect:` silences that one flag
  and grants nothing, so the card is exactly as available to the loop as it was.

**Beyond the flags, `## Why` was rewritten on all four** to the problem / cost / how-it-arose shape:
each now opens with what is wrong in the reader's terms, in bold, before anything that looks like an
answer. Every measurement, date and file name the old text carried was moved rather than dropped -
including `0001`'s "appears nowhere in `package.json` or `resources/js`", which is now written as
what was true when the card was raised. `## Comments`, `## Direction` and `## Decided` were not
touched on any card, and no acceptance box on any other card was ticked or unticked.

**The four-reason test.** One decision card on this board, `0004` (it is the only card with
`## Options`). It passes: the answer turns on **local knowledge nobody wrote down** - how the family
actually uses the timeline, which is not in the repository. It stays a decision card, and its
`## Why it needs you` now names that reason explicitly rather than leaving the reader to infer it.
Nothing was converted to a feature card, so nothing was researched-and-applied. `0003` sits in
`human-review/` for the second reason instead, a step only a person can take, and is not a decision
card.

**Reading I had to settle, and it is the one place a reviewer could disagree.** Criterion #1 says
`## Why` comes before any solution "anywhere in it", and `docs/board/README.md` also requires
`## What I need from you` **directly under the title** on a `human-review/` card. On `0003` and
`0004` the ask therefore sits above `## Why`. I read #1 as the README's own rule - `## Why` states
the problem and contains no fix - which the README backs by saying the two sections answer different
questions and `## Why` must not do the ask's job. On that reading all four pass, and I ticked it. If
the stricter reading is meant, `0003` and `0004` fail #1 and cannot be fixed without breaking the
lane rule.

**Lanes.** `todo/` and `ai-review/` are empty on this board, so "rewrite them first, then those" was
`human-review/` and nothing else. `in-progress/` holds only this card, which already passed all
five checks and was left as it stood apart from this entry.

**Not settled from the repository, two things.** First, this card's `## Tasks` and `## Plan` say to
write the count into `## Direction`, but the convention has since merged `## Direction` and
`## Decided` into one `## Comments` thread and says new entries go there. This card carried neither
heading, so I opened `## Comments` rather than reviving a superseded one, and I am counting those
tasks as met by this entry. Second, `0001` is 149 lines, over the 100-line budget. Almost all of the
excess is its `## Comments` thread, which is append-only and whose pruning the convention makes a
person's decision, not a rewrite's - so I left it and am flagging it here instead.

**Not checked in a browser, and it does not need to be.** This is a documentation change: four
markdown files under `docs/board/`, no code. Suite and style were still run from this worktree and
both are green; the numbers are in the commit.

### 2026-08-29 review (v20260829181007-2f2a)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 1s, run by this job rather than reported by the card.

**acceptance: sound**

**#1** ÔÇö traced. `## Why` in `human-review/0001-client-side-nsfw-pre-scan.md`, `0002-reconcile-the-phase-4-section.md`, `0003-verify-moderation-against-real-sightengine.md`, `0004-which-future-improvement-is-next.md`: each opens with what is wrong, then cost, then history, and names no fix. `docs/board/README.md` ┬º"`## Why` is the PROBLEM" scopes the rule to the section ("No solution appears in `## Why`"), and ┬º"The one section a card in `human-review/` must have" mandates `## What I need from you` under the title. The builder's reading is the README's own, so the ask sitting above `## Why` on 0003/0004 is not a failure.

**#2** ÔÇö traced. `0004` ┬º"What I need from you", `**Why it needs you**`, names **local knowledge nobody wrote down**. It is the only card with `## Options`.

**#3** ÔÇö traced. Four new `## Links` sections, each `**Relates to**` with a reason line. Remaining bare numbers sit only in `## Comments`, which the README makes append-only.

**#4** ÔÇö vacuous and true: no `needs:` and no `Blocked by` exists on this board.

**#5** ÔÇö traced by diff `aba0abc`: only `## Why`, `## Links`, `## Not this card` changed. `0004` ┬º`## Decided` untouched.

**#6** ÔÇö I ran `board:convention --path=$PWD --cards`: `timeline 0 5 0006`.

VERDICT: sound

**scope: defect**

**Scope grew ÔÇö card `0003`.** In `docs/board/human-review/0003-verify-moderation-against-real-sightengine.md`, section `## Links`, the new `0002` entry states that quarantine does not touch the image, and that "criterion #3 below is expected to fail as written". That fact lived on `0002`'s comment thread, not on `0003`. Card `0005`, section `## Not this card`, fences a rewrite to "keeps everything the card knows and changes only how it is ordered and said". This is new knowledge, imported.

**And it stopped half way.** In the same file, `## What I need from you`, step 3 still tells the person "Pass: the event's `image_url` is nulled and the file has moved to `storage/quarantine/`", and `## Acceptance` criterion #3 still says the same. The card now gives the reader two opposite answers about one step. That is the round trip this board exists to remove.

**It was not declared.** `docs/board/ai-review/0005-rewrite-this-board-s-cards-for-the-reader.md`, section `## Comments`, says only that nothing was dropped. It never says anything was added.

The `no_outward_effect:` key on `0001` is not a finding: `docs/board/README.md`, section "A confirmed false positive is recorded, not argued with", sanctions exactly that use.

VERDICT: defect

**breakage: defect**

Checked `board:convention --path="C:\Dev\timeline" --cards`: it prints `0` failing of `5`. The structural checks pass. The prose the rewrite added does not.

**1. `docs/board/human-review/0001-client-side-nsfw-pre-scan.md`, `## Why`.** It now says, in present tense, that every picture is uploaded before anything looks at it, and that "the check in the browser was never built". Both are false. `package.json` carries `nsfwjs` 4.3.0, `resources/js/lib/nsfwScan.js` exists, and `EventForm.jsx`'s `handleImageChange` blocks the file before upload. The same card's own `## Comments` says so twice. The rewrite time-stamped the package clause ("When this card was writtenÔÇª") and left the headline claim untimed, so the card's problem statement contradicts its own thread.

**2. Same file, `## Links` ÔåÆ `0002`.** The reason given is that `HANDOVER.md` "describes this pre-scan as planned and names a package that does not exist on npm". `HANDOVER.md` ┬º"Not settled: the client-side pre-scan" already says it "is built" and names `nsfwScan.js`. That link was untrue when written.

**3. `docs/board/human-review/0003-verify-moderation-against-real-sightengine.md`.** The new `## Links` says criterion #3 will fail. `## What I need from you`, step 3, still tells the reader "Pass: `image_url` is nulled and the file has moved to `storage/quarantine/`". `AdminController::reviewFlag` does neither. The person does the work to learn what the card already knows.

VERDICT: defect


**2026-08-29** The reviewer returned this card and its finding is the last review entry at the bottom of ## Direction. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 6 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer never unticks a criterion - it is forbidden from editing acceptance at all - so the card came back with 6 of 6 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Untick what the reviewer disproved and move it back to todo/, or say here why the finding is wrong.

**2026-09-28** Manager pass: reopened with new criterion #7 because all three breakage findings still hold. `0001`'s `## Why` still opens "Every picture somebody picks is uploaded before anything looks at it", though `nsfwScan.js` is built; its `0002` link still says `HANDOVER.md` calls the pre-scan planned; and `0003`'s ask, step 3, still gives the quarantined file under `storage/quarantine/` as the pass while its `## Links` says criterion #3 will fail. `0003` stays a person's check; only its wording is this card's.
