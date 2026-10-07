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

### 2026-10-07 review (v20261007054352-4581)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 34s, run by this job rather than reported by the card.

**acceptance: defect**

I checked all six criteria against the code. Five hold. Criterion #6 fails.

**#1 to #5 hold.**
- **#1:** `CommentController::store` saves the comment with its event, author and time.
- **#2:** `CommentController::visibleEvent` uses `Event::visibleIn`. A user who cannot see the event gets the same 404 as for a missing event.
- **#3:** `CommentController::index` returns only comments newer than `since`.
- **#4:** `CommentController::destroy` lets the author, an admin or owner, or a super admin delete. Anybody else gets 403.
- **#5:** the `comments-write` limiter is on the post route only. The 11th post gets 429.

**#6 can fail.** It is a manual check, but the code can make it fail. The earlier review found this bug, and it is still there.

The bug is in `resources/js/components/views/EventModal.jsx`, in `Comments` → `send`. When you post, `send` calls `merge([data.comment])`. `merge` then moves `latest.current` (the "newer than" time for the next poll) to the time of your own new comment. Any comment another member wrote after your last poll is older than that time. The next poll asks only for newer comments, so their comment never shows. You must close and open the pop-up again to see it.

This happens in the normal case: two people comment at about the same time. The fix: in `send`, add your comment to the list but do not move `latest.current`.

UNMET: #6 posting a comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.

VERDICT: defect

**scope: sound**

I'm the scope reviewer. I found nothing that crosses the fence. I found nothing left half done.

**What I checked**
- All of the comments work is in one commit, `c5f2499`, with 17 files. Every file belongs to this card. The other files in the big diff come from other cards' commits (0006, 0009, 0010, 0012).
- The "Not this card" fence holds. There are no WebSockets, Reverb, Pusher or long-running process. There are no notifications. There are no reactions, threads, comment edits or MCP comment tools.
- `Event::visibleIn` in `app/Models/Event.php` does not add scope. Plan step 1 asked for the visibility rule to be moved into one shared place.
- Card 0014 is a gap the builder found and wrote down. It is not built here, so the card did not grow.
- All five tasks are done: the migration, the routes, the modal polling, the tests, and the rebuilt `public/build/`.

**Outside my lens (just a note)**
The breakage reviewer's bug is real. `Comments` → `send` in `EventModal.jsx` calls `merge([data.comment])`. That moves `latest.current` forward. This is a bug, not a scope problem, so I write no `UNMET:` for it. Criterion #6 is manual and still open, so it stays with Rob.

VERDICT: sound

**breakage: defect**

I found one real fault, and it is the same fault the last review found. Nobody fixed it.

**The fault: `EventModal.jsx`, in the `Comments` component, the `send` and `merge` functions**

- `merge` moves the marker `latest.current` to the time of the newest comment it gets.
- `send` calls `merge([data.comment])` with your own new comment. That moves the marker to "now".
- The next poll asks only for comments newer than "now".
- So a comment that another member posted after your last poll, but before your post, never shows. You must close the pop-up and open it again to see it.

**Example**

1. You poll at 0 s.
2. Another member comments at 5 s.
3. You post at 8 s. The marker is now 8 s.
4. You never see the comment from 5 s.

The tests do not catch this. They only test the server, and the server works.

**The fix (small)**

In `send`, add the new comment to the list. Do not move `latest.current`.

UNMET: #6 posting your own comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.

VERDICT: defect

**acceptance**

- **#6 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: posting a comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.
- **#6 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: posting your own comment moves the client's `since` marker past any comment another member made since the last poll, so that comment never appears without a reload.

### 2026-10-07 review (v20261007071814-dab6)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 27s, run by this job rather than reported by the card.

**acceptance: defect**

Criteria #1 to #5 are met. The server code does what each one says:

- **#1:** `CommentController::store` saves the comment with the event, the author and the time. Before that, `CommentController::visibleEvent` checks that the user can see the event.
- **#2:** `CommentController::visibleEvent` checks membership, then uses `Event::visibleIn`. That is the same visibility rule that `EventController::index` uses. A user who cannot see the event gets the same 404 as for a missing event.
- **#3:** `CommentController::index` sends back only comments created after `since`.
- **#4:** `CommentController::destroy` lets the author, a group admin or owner, or a super admin delete. Anybody else gets a 403.
- **#5:** the `comments-write` limiter is on the post route only.

**#6 can fail. The bug the last two reviews found is still in the code.** It is in `resources/js/components/views/EventModal.jsx`, in `Comments`:

- `send` calls `merge([data.comment])` with your own new comment.
- `merge` then moves `latest.current` to that comment's time. `latest.current` is the "newer than" time for the next poll.
- So a comment another member posts after your last poll, but before your post, is older than that time. The next poll skips it. It shows only after you close and open the pop-up again.
- This happens when two people comment at about the same time. That is the case #6 is about.

The fix is small. In `send`, add your comment to the list, but do not move `latest.current`.

UNMET: #6 `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves the `since` time past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**scope: sound**

I checked what this card changed against what it asked for. Nothing goes past the fence.

**What I checked**
- All the comments work is in one commit, `c5f2499`, with 17 files. Every file belongs to this card: the migration, `CommentController`, `EventComment`, `Event::visibleIn`, the `comments-write` limiter in `bootstrap/app.php`, the routes, `EventModal.jsx`, the CSS, the tests, the rebuilt `public/build/`, `HANDOVER.md` and card 0014.
- The other files in the big diff come from other cards: 0006 (the pint tidy-up), 0009, 0010 and 0012. This card did not grow into them.
- The "Not this card" fence holds. There are no WebSockets, Reverb or Pusher. There are no notifications. There are no reactions, threads, comment edits or MCP comment tools.
- `Event::visibleIn` is not extra work. Plan step 1 asked for the visibility rule to live in one place.
- All five tasks are done.

**Outside my lens**
- The `since` bug that the breakage reviewer found is real. In `EventModal.jsx`, in `Comments`, `send` calls `merge`, and that moves `latest.current` past other people's comments. It is a bug, not a scope problem, so I write no `UNMET:` line for it.
- Criterion #6 is a manual check. It stays with you, Rob.

VERDICT: sound

**breakage: defect**

The bug is still there. Two reviews found it before me, and nobody fixed it.

**The bug: `resources/js/components/views/EventModal.jsx`, in `Comments`, the `send` and `merge` functions**

- `merge` moves `latest.current` to the time of the newest comment it gets. The next poll asks only for comments newer than `latest.current`.
- When you post, `send` calls `merge([data.comment])` with your own new comment. That moves `latest.current` to "now".
- Example:
  - You poll at 0 s.
  - Another member comments at 5 s.
  - You post at 8 s, so `latest.current` is now 8 s.
  - The comment from 5 s never shows. You must close and open the pop-up again to see it.
- This happens when two people talk at the same time. That is the normal way people use comments.
- The tests do not catch this. They test only the server, and the server works correctly.

**A smaller problem:** if someone else deletes a comment, it stays on your screen until you open the pop-up again.

**The fix:** in `send`, add the new comment to the list. Do not move `latest.current`.

UNMET: #6 posting your own comment moves the client's `since` marker past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**acceptance**

- **#6 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves the `since` time past any comment another member posted since the last poll, so that comment never shows without a reload.
- **#6 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: posting your own comment moves the client's `since` marker past any comment another member posted since the last poll, so that comment never shows without a reload.

### 2026-10-07 review (v20261007094001-b421)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 25s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code. Criteria #1 to #5 pass. Criterion #6 can fail.

**#1 to #5 pass (all in `app/Http/Controllers/CommentController.php`):**
- **#1:** `store` saves the comment with the event, the author and the time. It calls `visibleEvent` first.
- **#2:** `visibleEvent` checks that the user is a group member. Then it uses `Event::visibleIn`, which applies both visibility layers. If the user cannot see the event, the app gives the same 404 as for a missing event.
- **#3:** `index` sends back only comments created after `since`.
- **#4:** `destroy` lets the author, a group admin or owner, or a super admin delete a comment. Anybody else gets a 403.
- **#5:** the `comments-write` limit in `bootstrap/app.php` applies to the post route.

**#6 can fail. The bug that three earlier reviews found is still in the code.**
The bug is in `resources/js/components/views/EventModal.jsx`, in `Comments`. When you post, `send` calls `merge([data.comment])`. Then `merge` sets `latest.current` to the time of your new comment. The next poll asks only for comments newer than that time.

Example:
1. Your screen checks for new comments at 0 s.
2. Another member comments at 5 s.
3. You post at 8 s.
4. Your next check asks only for comments after 8 s. So the comment from 5 s never shows until you reload.

**The fix:** in `send`, add your comment to the list, but do not change `latest.current`.

UNMET: #6 `send` in `EventModal.jsx` sends your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**scope: sound**

I checked the scope only, which means: did the work do more than the card asked, or leave part of it undone?

**What I checked**
- All the comments work is in one commit, `c5f2499`. It touches 17 files, and every one of them belongs to this card. They are the migration, `CommentController`, `EventComment`, `Event::visibleIn`, the `comments-write` limit in `bootstrap/app.php`, the routes, `EventModal.jsx`, the CSS, a one-line change in `GroupTimeline.jsx`, the tests, the rebuilt `public/build/`, `HANDOVER.md` and card 0014.
- The other files in the large diff come from the commits of other cards (0006, 0009, 0010 and 0012). This card did not grow into them.
- The "Not this card" limits hold. There are no WebSockets, no Reverb, no Pusher and no process that must stay running. There are no notifications. There are no reactions, threads, comment edits or MCP comment tools.
- `Event::visibleIn` is not extra work. Plan step 1 asked for one shared copy of the visibility rule.
- All five tasks are done.

**Outside my lens**
The bug that the breakage reviews found in `Comments` → `send` in `EventModal.jsx` is a bug, not a scope problem. So I write no `UNMET:` line for it. Criterion #6 is a manual check, and you do it, Rob.

VERDICT: sound

**breakage: defect**

I found one real bug. Three reviews before me found the same bug. Nobody has fixed it yet.

**The bug is in `resources/js/components/views/EventModal.jsx`, in `Comments`, in `send` and `merge`.**

- `merge` sets `latest.current` to the time of the newest comment it gets.
- The next poll asks only for comments newer than `latest.current`.
- When you post, `send` calls `merge([data.comment])` with your own new comment. That sets `latest.current` to "now".

**Example:**
1. You poll at 0 s.
2. Another member comments at 5 s.
3. You post at 8 s. `latest.current` is now 8 s.
4. The 5 s comment never shows. You must close the pop-up and open it again.

This happens when two people comment at about the same time, which is the case #6 tests. The tests check only the server, so they cannot catch this.

**The fix is small:** in `send`, add your comment to the list, but do not change `latest.current`.

UNMET: #6 `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves the `since` time past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**acceptance**

- **#6 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: `send` in `EventModal.jsx` sends your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.
- **#6 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves the `since` time past any comment another member posted since the last poll, so that comment never shows without a reload.

### 2026-10-07 review (v20261007111039-c436)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 24s, run by this job rather than reported by the card.

**acceptance: defect**

I checked the code. Criteria #1 to #5 hold. The server code for them is in `CommentController`: `store`, `visibleEvent` (which uses `Event::visibleIn`), `index`, `destroy`, and the `comments-write` limit. The suite is green.

**#6 can fail. The bug that four earlier reviews found is still in the code.**

The bug is in `resources/js/components/views/EventModal.jsx`, in `Comments`:
- When you post a comment, `send` calls `merge([data.comment])`.
- `merge` then sets `latest.current` to the time of your new comment.
- The next poll asks only for comments newer than `latest.current`.

Here is how it fails:
1. Your pop-up polls at 0 s.
2. Another member comments at 5 s.
3. You post at 8 s.
4. Your next poll asks only for comments after 8 s, so it misses the 5 s comment.
5. The 5 s comment shows only after you close and open the pop-up again.

This happens when two people comment at about the same time. That is the case #6 is about.

**Fix:** In `send`, add your comment to the list, but do not change `latest.current`. One way is to give `merge` a flag that skips the marker update.

UNMET: #6 `send` in `EventModal.jsx` puts your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**scope: sound**

I checked scope only. The question is: did the work do more than the card asked, or leave part of it undone? The answer is no on both.

**What I checked**
- All the comments work is in one commit, `c5f2499`. Every file in it belongs to this card. The other files in the big diff come from cards 0006, 0009, 0010 and 0012.
- The only change in `GroupTimeline.jsx` is one line. It passes `canComment={isMember}` to `EventModal`, and comments need that. It does not add comments to the vertical timeline view.
- The "Not this card" list is not crossed. There is no WebSocket, Reverb, Pusher or long-running process. There are no notifications. There are no reactions, threads, comment edits or MCP comment tools.
- `Event::visibleIn` in `app/Models/Event.php` is not extra work. Plan step 1 asked for the visibility rule to live in one place.
- Card 0014 is only written down, not built.
- All five tasks are done.

**Outside my lens**
The `since` bug is still in `EventModal.jsx`, in `Comments`. When you post, `send` calls `merge`, and `merge` moves `latest.current` past comments from other members. This is a bug, not a scope problem, so I write no `UNMET:` line for it. The breakage reviewer covers it.

VERDICT: sound

**breakage: defect**

I tried to break the comments code. One bug is still there. Four earlier reviews found it, and it is not fixed.

**The bug**

It is in `resources/js/components/views/EventModal.jsx`, in `Comments`, in the `send` and `merge` functions.

- When you post a comment, `send` calls `merge([data.comment])`.
- `merge` then moves `latest.current` to the time of your new comment. `latest.current` is the "newer than" time for the next poll (the check for new comments every 12 s).
- Another member's comment can be older than your post but newer than your last poll. The next poll skips that comment.

**Example**

1. Your screen polls at 0 s.
2. Another member comments at 5 s.
3. You post at 8 s.
4. Your next poll asks only for comments after 8 s. You do not see the 5 s comment until you close and open the pop-up again.

The tests check only the server, so they do not find this bug.

**The fix:** in `send`, add your own comment to the list, but do not change `latest.current`.

**A smaller problem:** if another person deletes a comment, it stays on your screen until you open the pop-up again.

UNMET: #6 `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.

VERDICT: defect

**acceptance**

- **#6 was named by the acceptance lens and is not a ticked criterion here**, so nothing was changed: `send` in `EventModal.jsx` puts your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.
- **#6 was named by the breakage lens and is not a ticked criterion here**, so nothing was changed: `send` in `EventModal.jsx` passes your own new comment through `merge`, which moves `latest.current` past any comment another member posted since the last poll, so that comment never shows without a reload.


**2026-10-07** The reviewer's acceptance lens returned this card defect: I checked the code. Criteria #1 to #5 hold. The server code for them is in `CommentController`: `store`, `visibleEvent` (which uses `Event::visibleIn`), `index`, `destroy`, and the `comments-write` limit. The suite is green. The reviewer's scope lens returned this card sound: I checked scope only. The question is: did the work do more than the card asked, or leave part of it undone? The answer is no on both. The reviewer's breakage lens returned this card defect: I tried to break the comments code. One bug is still there. Four earlier reviews found it, and it is not fixed. The loop moved it from todo/ to human-review/ because it has bounced 5 times between todo and ai-review, which is the limit, so it is waiting on a person. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer reopens every criterion it reports unmet, and the reviews that sent this card back named no criterion they disproved, so it came back with 5 of 6 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Add or reopen the criterion the finding breaks and move it back to todo/, or say here why the finding is wrong.
