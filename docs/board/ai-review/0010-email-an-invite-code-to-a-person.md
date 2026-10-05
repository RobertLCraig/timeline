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
