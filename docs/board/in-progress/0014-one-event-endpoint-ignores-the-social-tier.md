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
- [ ] WHEN a member asks for one event above their social tier, THE APP SHALL answer exactly as for a missing event. proves: `test_one_event_above_the_viewers_tier_is_not_found`
- [ ] WHEN anybody asks for one event they cannot see, THE APP SHALL answer 404 with the same body as a missing event. proves: `test_a_hidden_event_answers_like_a_missing_one`
- [ ] WHEN an agent asks `get_timeline_event` for an event above its user's social tier, THE APP SHALL answer as for a missing event. proves: `test_mcp_get_event_respects_the_social_tier`
<!-- AC:END -->

## Plan
In `EventController::show`, find the event with `Event::visibleIn($group, $user)->whereKey($id)`
and answer `404 Event not found.` when it is null. Do the same in `GetTimelineEventTool`. Check whether `isVisibleTo()` then has any
caller left before deciding to keep it.
