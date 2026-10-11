---
not_for_the_loop: creates a mail credential and puts it on the live Hostinger server
---
# A Fastmail app password so the live timeline can send invite mail

## What I need from you
1. Make a Fastmail SMTP app password for the timeline and save it to `C:\Dev\_secrets\timeline-fastmail.txt`. In Fastmail: Settings, Privacy & Security, "Connected apps & API tokens", "Manage app passwords and access", "New app password". Name it `timeline app (Hostinger)`, set access to **SMTP** only, press "Generate password". **Pass:** the file exists and holds the password.

Paste to answer: `**2026-10-11** **Decided:** app password made, in C:\Dev\_secrets\timeline-fastmail.txt.`

## What you need to know
- Invite emails are built (`0010`), but nobody has checked whether the live site can send mail. Nothing in the repository records mail settings for it.
- Your domain's mail already runs through Fastmail. The calendar app and enhanceify-V2 send through it and pass your domain's strict anti-forgery checks, so this needs no new provider and no DNS change.
- One app password per app means you can revoke one without breaking the others. SMTP-only means a leaked password can send mail but cannot read any.
- The phone-app question is answered (D, make the site installable) and is now card `0015`.

## See it
- Fastmail: https://app.fastmail.com (Settings, Privacy & Security). The steps come from https://www.fastmail.help/hc/en-us/articles/360058752854
- Live site: https://timeline.enhanceify.co.uk

---
## For the agent (Rob can stop reading here)
An attended session, after the answer: on the Hostinger server, set these values in the live `.env`, then run `php artisan config:clear`:
`MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, `MAIL_HOST=smtp.fastmail.com`, `MAIL_PORT=465`, `MAIL_USERNAME=r@enhanceify.co.uk`, `MAIL_PASSWORD=<the file>`, `MAIL_FROM_ADDRESS=timeline@enhanceify.co.uk`.
This shape is copied from calendar `0010` (done) and enhanceify-V2 `0015`. The `*@enhanceify.co.uk` wildcard alias covers the From address. Do not put the password on a command line. Send one invite to `r@craig.ooo` and ask Rob whether it arrived. Calendar's first mail landed in Junk, so check there too.

**Relates to** `0004` (the ranking that raised both questions), `0010` (invite emails, built against this mail setup) and `0015` (the installable site, Rob's phone-app answer).

## Comments
**2026-10-07** Condensed for Rob. Question 1 was rewritten. The enhanceify.co.uk zone already sends through Fastmail (calendar `0010`, `docs/DECISIONS.md` 2026-09-12), so options A and B are gone and only the app password is left.

**2026-10-11** **2026-10-11** Phone app: D, make the site installable (Rob). The Fastmail SMTP app password is still owed.

**2026-10-11** **Decided:** phone app D, make the site installable. Split into `todo/0015` (web manifest and service worker, installable). This card stays in `human-review/` for the Fastmail SMTP app password only, and its ask now says just that.
