# Email an invite code to a person

## Why
**To bring a relative onto the timeline, an admin copies an invite code and sends it by hand.**
The code is made in Group Settings, then pasted into a text, a WhatsApp or an email, along with an
explanation of where to go and what to do with it.

**What it costs.** Every new family member costs the admin a hand-written message, and the code
arrives with no link and no instructions. That friction limits how many of the family are actually
on the timeline.

**How it came to be this way.** Invite codes (`group_invites`, `GroupController::createInvite`)
were built to be shared by hand, and sending them was left for later.

## Links

**Relates to**
- `0004` - the decision that ranked invite emails second of the five future improvements.
- `0013` - asks which address the mail is sent from and whether mail reaches people from
  production today. The code here does not wait for it; going live does.

## Not this card
- Choosing or configuring a mail provider, DNS records (SPF, DKIM) or production `.env` values.
  That is a person's, on card `0013`.
- Sending a real email from any machine. Tests use `Mail::fake()` / `Notification::fake()`.
- Reminder or resend schedules, open tracking, or a mailing list.
- Changing how a code is redeemed.

## Acceptance
<!-- AC:BEGIN -->
- [x] WHEN a group owner or admin creates an invite with an email address, THE APP SHALL send one message to that address carrying the code and a link to join. proves: `test_an_invite_with_an_email_sends_the_code`
- [x] WHEN an invite is created without an email address, THE APP SHALL send nothing and behave as it does today. proves: `test_an_invite_without_an_email_sends_nothing`
- [x] WHEN a plain member tries to send an invite email, THE APP SHALL refuse. proves: `test_a_member_cannot_email_an_invite`
- [x] WHEN the address is not a valid email, THE APP SHALL refuse with a validation error and create no invite. proves: `test_an_invite_to_a_bad_address_is_rejected`
- [x] WHEN one user sends invite emails faster than the limit, THE APP SHALL refuse the extra ones. proves: `test_invite_emails_are_rate_limited`
- [x] WHEN the mailer throws, THE APP SHALL still create the invite and tell the user the email did not go. proves: `test_a_failed_send_still_creates_the_invite`
<!-- AC:END -->

## Tasks
- [ ] Optional `email` on `POST /api/groups/{slug}/invites`
- [ ] A notification or mailable carrying code, group name, inviter name and join link
- [ ] A per-user throttle on that route
- [ ] Email field in the invite form in `GroupSettings.jsx`
- [ ] Tests named above
- [ ] `npm run build` and commit `public/build/`

## Plan
Stand in the timeline repository on the card's branch. Read `CLAUDE.md` first.

1. The route already exists: `POST /api/groups/{slug}/invites` in `routes/api.php`, handled by
   `GroupController::createInvite` (`app/Http/Controllers/GroupController.php`). It is already
   gated to owner/admin by its route middleware; confirm that by reading the route group, and test
   it.
2. **Mail is not new to this app.** `User` implements `MustVerifyEmail` and
   `AuthController::resendVerification` sends verification mail, so the mailer config in
   `config/mail.php` and `.env` is what this card uses too. Do not add a mail package.
3. Queue the message if a queue worker runs on production; read `deploy.sh` and `HANDOVER.md` to
   find out. If nothing runs the queue there, send synchronously inside a `try`, so a mail failure
   never loses the invite (criterion 6).
4. The join link is the group page, `/g/{slug}`, where a signed-in user enters a code. Check
   `routes/web.php` and `GroupTimeline.jsx` for whether a code can ride in the URL; if it cannot,
   the email gives the link and the code separately. Do not build a new redemption route here.
5. Throttle with a named `RateLimiter` in `bootstrap/app.php`, beside the existing `events-write`
   limiter. 10 per user per hour is the default to apply; it protects the sending reputation, not
   a person's preference.
6. Run `composer test`; it must be green.

## Comments

**2026-10-05** RESULT: done
TESTS: +6 new, all green (48 in the suite)
TOUCHED: app/Http/Controllers/GroupController.php
app/Notifications/GroupInviteNotification.php
bootstrap/app.php
routes/api.php
resources/js/pages/GroupSettings.jsx
tests/Feature/InviteEmailTest.php
public/build/ (rebuilt assets)
OUT-OF-SCOPE: none

Built: optional `email` on POST /api/groups/{slug}/invites. With an email, a GroupInviteNotification (code, group name, inviter, link to /g/{slug}) goes to that address and the reply carries `email_sent: true|false`. Without one, the reply is unchanged. Throttle `invite-email` in bootstrap/app.php: 10 per user per hour, counted only when an email is given. GroupSettings.jsx has an optional email field and shows sent / not sent / validation messages.

Decisions from the repository: deploy.sh runs no queue worker, so the mail is sent synchronously inside try/catch (report() then email_sent:false). GroupTimeline has no way to take a code from the URL, so the mail gives the link and the code separately.

Test-first: criteria 1, 4, 5, 6 were watched failing for their stated reason before the code. Criteria 2 and 3 passed before any code (today nothing sends, and the route was already gated to owner/admin), so I proved them by mutation: sending with no email failed test_an_invite_without_an_email_sends_nothing; moving the route into the member group failed test_a_member_cannot_email_an_invite. Both restored. Criterion 6 uses a mailer name that does not exist, which makes the mailer throw; it does not exercise an SMTP-level failure.

Notes: vendor/bin has no pest.bat; the suite is PHPUnit, run with `php artisan test`. `npm run build` first failed because node_modules (here and in C:\Dev\timeline) lacked nsfwjs although package-lock.json lists it; `npm install` from the lock fixed it here, and C:\Dev\timeline needs the same before its next build. A throttled request also counts a bad-address attempt toward the 10, as the throttle runs before validation.

Still needs a person: a browser check of the Group Settings form (Herd serves C:\Dev\timeline, not this worktree), and card 0013 before mail can reach anyone from production.

### 2026-10-07 review (v20261007020540-fdf2)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 20s, run by this job rather than reported by the card.

**acceptance: sound**

I checked all six acceptance criteria against the code and could not break any of them.

1. **An email goes out with the code and a join link.** `GroupController::createInvite` sends one `GroupInviteNotification` to the address. The test checks the address, the code in the text and the link to `/g/{slug}`.
2. **No email means nothing is sent.** `createInvite` returns early when there is no `email`. The reply has no `email_sent` field, so it is the same as before.
3. **A plain member is refused.** The route in `routes/api.php` sits inside the `group.role:owner,admin` group. The test gets 403, no mail and no invite.
4. **A bad address is refused.** The `email` rule in `createInvite` runs before `GroupInvite::create`. The test gets 422 and no invite row.
5. **Too many emails are refused.** The `invite-email` limiter in `bootstrap/app.php` allows 10 per user per hour, and the route uses it. Invites with no email are not counted. The test gets 429 on the 11th.
6. **A mail failure still keeps the invite.** In `createInvite`, a `try`/`catch` around `notifyNow` calls `report()` and returns `email_sent: false`. The invite is already saved by then. `GroupSettings.jsx` shows "the email did not go" and gives the code to send by hand.

Two small notes. Neither one breaks a criterion:
- The failure test uses a mailer name that does not exist. It does not test a real SMTP (mail server) error. The `catch (\Throwable)` still covers that case.
- A bad-address request still counts toward the limit of 10, because the limit is checked before the address.

VERDICT: sound

**scope: sound**

I checked the scope of this card's own work. I found no change over the "Not this card" fence, and nothing left half done.

- **`GroupController::createInvite`**: Adds only the optional `email` field. It sends now, inside `try`. It does not change how a code is redeemed. A request with no email gets the same reply as before.
- **The `invite-email` limiter in `bootstrap/app.php` and the route in `routes/api.php`**: The limiter is per user, 10 per hour, and counts only requests that carry an email. It sits on the existing route, which is in the owner/admin group. It adds no new route.
- **`GroupInviteNotification`**: Uses the mailer the app already has. It adds no mail package, no `.env` or provider change, no resend, and no tracking.
- **The other files in the diff**: The Pint reformatting, photos (0009), comments (0011) and export (0012) come from other cards on the same branch. This card did not make them. Its "TOUCHED" list does not name them.
- **All six tasks are in place**, and the test file exists. The suite is green.

The browser check of the Group Settings form is a person's task. It is not a finding. I did not disprove any criterion.

VERDICT: sound

**breakage: sound**

I tried to break the invite email work. I could not break it, so the verdict is sound.

**What I checked:**
- **Only admins can send.** The route is in the owner/admin group in `routes/api.php`. A plain member gets 403 before anything is sent.
- **The rate limit.** The `invite-email` limit in `bootstrap/app.php` allows 10 emails per user per hour. It counts only requests that carry an email. Its comment is still true.
- **A failed send.** `GroupController::createInvite` creates the invite first, then tries to send. If the mailer throws, it still returns the invite with `email_sent: false`. Without an email, the reply is the same as before.
- **The join link works.** The link goes to `/g/{slug}`. The group page shows any group to anyone (the server function `GroupController::show` does not block it). If the person is signed in and not a member, `GroupTimeline.jsx` shows a "Join this group" box with a code field. The email text in `GroupInviteNotification::toMail` says exactly that.
- **The form.** `GroupSettings.jsx` reads `email_sent` and shows the matching message. It also shows the email error from the server.

**One gap, not a defect:** the MCP tool `CreateGroupInviteTool` cannot send an email. The card asks only for the web route, so no criterion fails. `AGENTS.md` makes no claim about email, so it is still true.

No criterion is disproved, so there are no `UNMET:` lines.

VERDICT: sound

