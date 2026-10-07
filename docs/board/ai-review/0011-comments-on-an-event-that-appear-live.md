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

### 2026-10-07 review (v20261007031447-f4f3)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 24s, run by this job rather than reported by the card.

**acceptance: sound**

I tried to break each of the six acceptance criteria and could not. The project's own test suite was green before I started.

- **#1 Post a comment:** `CommentController::store` saves the comment with the event, the author and the time. `CommentController::visibleEvent` checks the user can see the event first. The test checks all three saved values.
- **#2 Hidden event:** `CommentController::visibleEvent` uses `Event::visibleIn`, which checks both visibility layers. The timeline query in `EventController::index` uses the same rule, so there is one copy. A user who cannot see the event gets a 404 with the same body as a missing event. The test covers four cases: an outsider, a private event, an event above the user's social tier, and an event that does not exist.
- **#3 Only newer comments with `since`:** `CommentController::index` returns only comments created after the `since` time. A quiet poll returns an empty list, and the test checks this.
- **#4 Delete:** `CommentController::destroy` lets the author, a group admin or owner, or a super admin delete. Any other user gets a 403. The test checks a plain member, the author, an admin and the owner.
- **#5 Rate limit:** the `comments-write` limiter in `bootstrap/app.php` applies only to the post route in `routes/api.php`. The 11th post gets a 429.
- **#6 Show without a reload:** this needs a person to test it in two browsers. The code can pass it. `EventModal.jsx` polls on a timer, stops when the tab is not visible, and sends `since`. One limit: the vertical timeline view does not use `EventModal`, so to test it, use the calendar, heatmap or photos view.

VERDICT: sound

**scope: sound**

I found nothing in the comments work that goes past what the card asked for.

**What I checked:**
- The comments work is one commit, `c5f2499`. All of its 17 files belong to this card. They are the migration, `CommentController`, `EventComment`, the `comments-write` limit in `bootstrap/app.php`, the routes, `EventModal.jsx`, the CSS, the tests, the rebuilt `public/build/`, `HANDOVER.md` and the new card 0014.
- The large diff also holds other cards' commits. These are the pint clean-up (0006), several photos (0009), invite email (0010) and export (0012). They are not this card's growth.
- The "Not this card" list is not crossed. There is no WebSocket, Reverb or Pusher. There are no notifications. There are no reactions, threads, comment edits or MCP comment tools.
- `Event::visibleIn` in `app/Models/Event.php` moves the rule that `EventController::index` used into one shared place. Plan step 1 asked for this, so it is not extra work.
- The migration only adds a new table. When an event is deleted, its comments are deleted too (`cascadeOnDelete`).

**Things still open, but not defects:**
- The vertical timeline view does not use `EventModal`, so comments do not show there. The card named only `EventModal.jsx`, and the builder wrote this gap down.
- People who are not members of the group cannot read comments on a public event. The card says "a group member", so this matches the card.
- Criterion #6 is a manual test with two browsers. That is your test, Rob, and nothing in the code stops it from passing.

No criterion is disproved.

VERDICT: sound

**breakage: defect**

I found one real problem. Your own comment can hide another member's comment.

**The fault (`resources/js/components/views/EventModal.jsx`, `Comments` → `send` and `merge`)**

- `merge` moves the `since` marker (`latest.current`) to the time of the newest comment it adds.
- `send` calls `merge([data.comment])` with your own new comment. That moves the marker forward to now.
- Example:
  - You poll at 0 s.
  - Another member comments at 5 s.
  - You post at 8 s, so the marker is now 8 s.
  - Your next poll asks only for comments newer than 8 s.
  - The comment from 5 s never shows. You must close and open the pop-up again to see it.
- This happens in the normal case: two people talking at the same time. The tests do not catch it. They only test the server, and the server does the right thing for the `since` value it gets.

**Small extras (not criterion failures)**

- If someone else deletes a comment, it stays on your screen until you open the pop-up again.
- The "second-precision" note is not the only way to lose a comment. The marker jump above loses more.

**The fix**

Option A: in `send`, add the new comment to the list but do not move `latest.current`. Then the next poll gets everything newer than the last poll.

Option B: poll by comment id (`after_id`), not by time.

I would choose option A. It is the smaller change.

UNMET: #6 posting your own comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.

VERDICT: defect

**acceptance**

- **#6 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: posting your own comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.

