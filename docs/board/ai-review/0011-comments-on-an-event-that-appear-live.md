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
- [x] WHEN a group member who can see an event posts a comment, THE APP SHALL store it against that event with the author and time. proves: `test_a_member_can_comment_on_an_event_they_can_see`
- [x] WHEN a user who cannot see an event asks for or posts its comments, THE APP SHALL refuse without saying whether the event exists. proves: `test_comments_on_a_hidden_event_are_not_reachable`
- [x] WHEN comments are fetched with a `since` time, THE APP SHALL return only the newer ones. proves: `test_comments_since_returns_only_newer_comments`
- [x] WHEN a comment's author or a group admin/owner deletes it, THE APP SHALL remove it; WHEN anybody else tries, THE APP SHALL refuse. proves: `test_only_the_author_or_an_admin_can_delete_a_comment`
- [x] WHEN one user posts comments faster than the limit, THE APP SHALL refuse the extra ones. proves: `test_comment_posting_is_rate_limited`
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

## Comments

**2026-10-05** RESULT: partial
TESTS: +5 new, all green (53 tests, phpunit)
TOUCHED: app/Http/Controllers/CommentController.php
app/Http/Controllers/EventController.php
app/Models/Event.php
app/Models/EventComment.php
bootstrap/app.php
routes/api.php
database/migrations/2026_10_05_000200_create_event_comments_table.php
tests/Feature/EventCommentsTest.php
resources/js/components/views/EventModal.jsx
resources/js/components/views/views.css
resources/js/pages/GroupTimeline.jsx
public/build/ (rebuilt assets + manifest)
HANDOVER.md
docs/board/todo/0014-one-event-endpoint-ignores-the-social-tier.md
OUT-OF-SCOPE: 0014

Built: an additive `event_comments` table, CommentController with list (`?since=`), post and delete under auth:sanctum, a `comments-write` limiter (10/min per user) beside `events-write`, and a Comments block in EventModal that polls every 12 s only while the pop-up is open and `document.visibilityState` is `visible`. Bodies render as React text, never HTML.

Test-first: all five tests were run red before the code. With only the table and model they failed on 405 (no route). Against a naive controller with no checks, 2-5 each failed on their own assertion: 200 not 404 for a hidden event, `since` returning both comments, 200 not 403 for another member's delete, 201 not 429 on the 11th post. Then green.

Criterion 6 is NOT met: it is a manual two-browser check on timeline.test, and Herd serves C:\Dev\timeline, not this worktree. It still needs a person after the merge: open an event in the calendar, heatmap or photos view in two browsers as two members, comment in one, and see it appear in the other within about 12 s. Note: the vertical timeline view renders cards inline and does not use EventModal, so comments do not show there. The card named EventModal.jsx, so I did not widen it.

Plan step 1: the 'can this user see this event' rule lived only inside EventController::index (Event::isVisibleTo checks the legacy layer alone). I moved that query unchanged into `Event::visibleIn($group, $user)` and index now calls it, so there is one copy. Existing suite still green.

Plan step 2 said to return 404 'the same as the event endpoint does'. The event endpoint (EventController::show) actually answers 403 for a hidden event and skips the social tier. Comments answer 404 with the same body as a missing event. The show gap (and the same gap in the MCP get_timeline_event tool) is card 0014.

Assumed: comments are for group members (plus super admin) only. A non-member who can see a public event on a public group gets 404 on its comments. The card says 'a group member', so I read it that way; Rob may want public readers to see comments.

Known ceiling, marked with a ponytail: comment: `since` is second-precision, so a comment written in the same second as the client's newest one can be missed by the next poll. The client de-duplicates by id.

Worktree notes: there is no Pest in this repo; the suite is `.\vendor\bin\phpunit.bat` (pest.bat does not exist). The copied node_modules was stale (`nsfwjs/core` unresolved); `npm ci` fixed it and `npm run build` passed. pint passes on every PHP file touched.
