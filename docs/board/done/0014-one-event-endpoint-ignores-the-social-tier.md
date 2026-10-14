# The one-event endpoint ignores the social tier and confirms hidden events exist

## Why
**`GET /api/groups/{slug}/events/{id}` shows an event the timeline hides.** `EventController::show`
checks only `Event::isVisibleTo()`, which is the legacy `public`/`members`/`private` layer. It never
applies the social tier. A member whose tier for the group is `friends` cannot see a `family` event on
the timeline, but can read it in full by asking for its id.

**It also says the event is there.** A hidden event answers `403 Access denied.` and a missing one
answers `404 Event not found.`, so counting ids tells a stranger which events exist.

**What it costs.** The social tier is the app's privacy promise, and this endpoint breaks it for
anyone who guesses an id. Ids are sequential.

**How it came to be this way.** `isVisibleTo()` says in its own comment that the social tier "is
applied at the query level in EventController", but only `index` applied it. Card `0011` moved that
query into `Event::visibleIn()` for comments; `show` was outside that card.

## Links

**Relates to**
- `0011` - built `Event::visibleIn()`, the rule `show` should use.

The MCP tool `get_timeline_event` (`app/Mcp/Tools/GetTimelineEventTool.php`) makes the same
`isVisibleTo()` check and has the same tier gap.

## Not this card
`update` and `destroy` (they check ownership, not visibility). `ListTimelineEventsTool` keeps its own
copy of the tier rule; moving it onto `visibleIn()` is tidy-up, not this fault.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN a member asks for one event above their social tier, THE APP SHALL answer exactly as for a missing event. proves: `test_one_event_above_the_viewers_tier_is_not_found`
- [x] WHEN anybody asks for one event they cannot see, THE APP SHALL answer 404 with the same body as a missing event. proves: `test_a_hidden_event_answers_like_a_missing_one`
- [x] WHEN an agent asks `get_timeline_event` for an event above its user's social tier, THE APP SHALL answer as for a missing event. proves: `test_mcp_get_event_respects_the_social_tier`
<!-- AC:END -->

## Plan
In `EventController::show`, find the event with `Event::visibleIn($group, $user)->whereKey($id)`
and answer `404 Event not found.` when it is null. Do the same in `GetTimelineEventTool`. Check whether `isVisibleTo()` then has any
caller left before deciding to keep it.

## Comments

**2026-10-14** RESULT: done
TESTS: +3 new, all green (67 passed); pint pass
TOUCHED: app/Http/Controllers/EventController.php, app/Mcp/Tools/GetTimelineEventTool.php, app/Models/Event.php, HANDOVER.md, tests/Feature/EventShowVisibilityTest.php, tests/Feature/McpAuthorizationTest.php
OUT-OF-SCOPE: none

Each test was watched red first for the card's reason: #1 got 200 for a family event shown to a friends-tier member; #2 got 403 (not 404) for a guest on a members-only event; #3 the MCP tool returned the family event with no error.

Built as the Plan says. `EventController::show` now finds the event with `Event::visibleIn($group, $user)->whereKey($id)` and answers `404 Event not found.` when it is null, so hidden and missing are the same answer (the old 403 `Access denied.` is gone). `GetTimelineEventTool` checks `Event::visibleIn($event->group, $user)->whereKey($event->id)->exists()`; its error text was already the same for hidden and missing, and the test proves both now come out that way.

`Event::isVisibleTo()` had no caller left, so I deleted it; its comment ('social tier is applied at the query level in EventController') was the wrong claim that let this happen. Nothing in resources/js reads the old 403. HANDOVER.md's visibility section now names the one-event reads as users of `visibleIn`.

Note: the brief says `.\vendor\bin\pest.bat`, but this repo has no Pest; I ran `php artisan test` (PHPUnit) with Herd's php84\php.exe. Not browser-checked: Herd serves C:\Dev\timeline, not this worktree, so the event pop-up still needs one look in a browser after merge.

### 2026-10-15 review (v20261015001826-5a12)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 38s, run by this job rather than reported by the card.

**acceptance: sound**

I checked all three criteria against the code. I could not break any of them.

**#1. An event above your tier answers as "not found".**
- `EventController::show` now finds the event through `Event::visibleIn($group, $user)->whereKey($id)`. If that finds nothing, it sends `404 Event not found.`
- `Event::visibleIn` step 2 hides an event above a member's group tier. If no tier is set, the tier is `friends`.
- The test `test_one_event_above_the_viewers_tier_is_not_found` asks for a `family` event and gets exactly the "not found" body. It also asks for a `friends` event, and that event still opens.

**#2. Anyone who cannot see an event gets the same 404 as for a missing one.**
- The old 403 branch and `Event::isVisibleTo` are gone. `show` has only one "no" answer now.
- The test `test_a_hidden_event_answers_like_a_missing_one` checks four people: a guest, an outsider, a member asking for another person's private event, and a member below the event's tier. Each one gets the same status and body as a missing id.

**#3. The MCP tool keeps the tier too.**
- `GetTimelineEventTool::handle` now checks `Event::visibleIn($event->group, $user)->...->exists()`. A hidden event and a missing event get the same error text.
- The test `test_mcp_get_event_respects_the_social_tier` proves this. It also checks that the hidden title does not leak.

The suite was green, so no code still calls the deleted `isVisibleTo`.

VERDICT: sound

**scope: sound**

I checked the scope of this card. I found no problem.

**What the card's own commit changed.** Commit `7340581` changes six files:
- `EventController::show`: it now uses `Event::visibleIn`. A hidden event now gets the same 404 as a missing event.
- `GetTimelineEventTool::handle`: it now does the same check.
- `Event::isVisibleTo`: removed. The card's Plan asked the builder to check for callers first. A search of `app`, `tests`, `resources` and `routes` finds no callers left.
- `HANDOVER.md`: the visibility section is updated.
- Two test files. They hold the three tests that the card names.

The builder reported the same six files.

**The fence holds.**
- `update` and `destroy` did not change.
- `ListTimelineEventsTool` did not change.

**The other files in the diff are from other cards.** These include the export controller (card 0012), the photo changes (0009), the installable-site files (0015) and the board files. Card 0014's commit does not touch any of them.

**Nothing is half done.** The old 403 `Access denied.` reply is gone from both read paths. The front-end code does not read that 403.

I found no criterion that fails.

VERDICT: sound

**breakage: sound**

**Verdict: sound.** I tried to break this card's change and could not.

- **No caller is left on the old check.** I searched for `isVisibleTo` across the code. It is gone, and only board cards still name it. `EventController::show` and `GetTimelineEventTool::handle` now use `Event::visibleIn`, the same rule the timeline and comments use.
- **The doc comments are now true.** The docblock on `Event::visibleIn` and the visibility section in `HANDOVER.md` name the one-event reads correctly.
- **The front end still works.** `EventForm.jsx` loads the event with GET `/groups/{slug}/events/{id}`. Nothing in `resources/js` reads the old 403.
- **One new edge, outside this card.** In `Event::visibleIn`, a plain member sees their own event above their tier only when its tier is `private`. An example: a `friends`-tier member posts a `family` event. Before this change, that member could open their own event's edit page by its URL, and the MCP `get_timeline_event` returned it. Now both answer "not found".
- **Why it does not stop this card.** The timeline already hides that event from that member, so the change makes the reads agree with the one shared rule. The member can still change or delete the event, because `update` and `destroy` check ownership. If the creator should always see their own events, change `Event::visibleIn`, the rule itself, on a new card.

No criterion is disproved.

VERDICT: sound

