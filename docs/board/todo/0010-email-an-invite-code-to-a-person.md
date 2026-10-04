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
- [ ] WHEN a group owner or admin creates an invite with an email address, THE APP SHALL send one message to that address carrying the code and a link to join. proves: `test_an_invite_with_an_email_sends_the_code`
- [ ] WHEN an invite is created without an email address, THE APP SHALL send nothing and behave as it does today. proves: `test_an_invite_without_an_email_sends_nothing`
- [ ] WHEN a plain member tries to send an invite email, THE APP SHALL refuse. proves: `test_a_member_cannot_email_an_invite`
- [ ] WHEN the address is not a valid email, THE APP SHALL refuse with a validation error and create no invite. proves: `test_an_invite_to_a_bad_address_is_rejected`
- [ ] WHEN one user sends invite emails faster than the limit, THE APP SHALL refuse the extra ones. proves: `test_invite_emails_are_rate_limited`
- [ ] WHEN the mailer throws, THE APP SHALL still create the invite and tell the user the email did not go. proves: `test_a_failed_send_still_creates_the_invite`
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
