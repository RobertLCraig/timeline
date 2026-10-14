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
