# Comments on an event that appear without a reload

## Why
**Nobody can say anything about an event.** A family member who sees an old photo and remembers
who took it, or what happened next, has nowhere on the timeline to write it down. The talk happens
in a group chat and is lost.

**What it costs.** The memories the timeline exists to hold are the ones it cannot collect. Every
event is one person's account, frozen.

**How it came to be this way.** Comments were never built. There is no comment table, route or
screen in the app today.

## Links

**Relates to**
- `0004` - the decision that ranked real-time comments third of the five future improvements.

## Not this card
- WebSockets, Laravel Reverb, Pusher or any process that must stay running. Production is Hostinger
  shared hosting, which cannot keep one alive, so "live" here means short polling.
- Email or push notifications about new comments.
- Reactions, threads, editing a comment, or comments through MCP.

## Acceptance
<!-- AC:BEGIN -->
- [ ] WHEN a group member who can see an event posts a comment, THE APP SHALL store it against that event with the author and time. proves: `test_a_member_can_comment_on_an_event_they_can_see`
- [ ] WHEN a user who cannot see an event asks for or posts its comments, THE APP SHALL refuse without saying whether the event exists. proves: `test_comments_on_a_hidden_event_are_not_reachable`
- [ ] WHEN comments are fetched with a `since` time, THE APP SHALL return only the newer ones. proves: `test_comments_since_returns_only_newer_comments`
- [ ] WHEN a comment's author or a group admin/owner deletes it, THE APP SHALL remove it; WHEN anybody else tries, THE APP SHALL refuse. proves: `test_only_the_author_or_an_admin_can_delete_a_comment`
- [ ] WHEN one user posts comments faster than the limit, THE APP SHALL refuse the extra ones. proves: `test_comment_posting_is_rate_limited`
- [ ] WHEN another member comments while the event pop-up is open, THE APP SHALL show it within the polling interval without a reload. proves: manual - two browsers on `timeline.test`
<!-- AC:END -->

## Tasks
- [ ] Additive migration for comments
- [ ] List, post (with `since`) and delete routes under `auth:sanctum`
- [ ] Comment list and box in `EventModal.jsx`, polling while it is open and the tab is visible
- [ ] Tests named above
- [ ] `npm run build` and commit `public/build/`

## Plan
Stand in the timeline repository on the card's branch. Read `CLAUDE.md` first, especially "Social
Visibility System": who may see an event is decided by two layers (legacy `visibility` and the
social tier), and comments must follow exactly the same rule.

1. **Find the existing "can this user see this event" check** in `EventController` (the show
   action and the group timeline query) and reuse it. Do not write a second copy of the rule.
2. Return 404 for an event the user cannot see, the same as the event endpoint does, so a comment
   route does not leak that an event exists.
3. Delete permission mirrors the event edit rule in `CLAUDE.md` ("Authorization rule"): author, or
   group admin/owner, or super admin.
4. **Polling** is the applied choice, because shared hosting cannot hold a socket open. Poll every
   10 to 15 seconds only while the modal is open and `document.visibilityState` is `visible`, and
   send `since` so a quiet poll returns an empty list.
5. Store comment text as plain text and render it as text, never as HTML.
6. Throttle posting with a named limiter in `bootstrap/app.php`, beside `events-write`.
7. Run `composer test`; it must be green.
