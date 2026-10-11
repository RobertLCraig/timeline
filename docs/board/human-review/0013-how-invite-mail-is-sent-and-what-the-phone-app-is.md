---
not_for_the_loop: creates a mail credential and puts it on the live Hostinger server
---
# A mail password for the timeline, and what "the phone app" should be

## What I need from you
1. In Fastmail, go to Settings, Privacy & Security, "Connected apps & API tokens", then "Manage app passwords and access", and press "New app password". Name it `timeline app (Hostinger)` and change the access from the default to **SMTP** only. Press "Generate password". Save it in `C:\Dev\_secrets\timeline-fastmail.txt`. **Pass:** the file exists and holds the password.
2. For the phone app, pick **C**, **D** or **E**.

**My recommendation:** do 1, and pick **D** for 2.
Paste to answer: `**2026-10-07** **Decided:** 1 - app password made, in C:\Dev\_secrets\timeline-fastmail.txt. 2 - D, make the site installable.`

## What you need to know
- Invite emails are built (`0010`), but nobody has checked whether the live site can send mail. Nothing in the repository records mail settings for it.
- Your domain's mail already runs through Fastmail. The calendar app and enhanceify-V2 send through it and pass your domain's strict anti-forgery checks. Reusing Fastmail needs no new provider and no DNS change. The options this card used to offer, a Hostinger mailbox or a sending service, each needed new DNS records before they could pass those checks.
- One app password per app means you can revoke one without breaking the others. SMTP-only means a leaked password can send mail but cannot read any.
- **C, a React Native app.** A second app to build and keep. Costs $99 a year for Apple and $25 once for Google, plus store reviews.
- **D, make the website installable.** A home-screen icon, full screen, no browser bar. One small card, no fees. Push notifications do not work on every phone.
- **E, drop it.** The site already works in a phone browser.

## See it
- Fastmail: https://app.fastmail.com (Settings, Privacy & Security). The steps come from https://www.fastmail.help/hc/en-us/articles/360058752854
- Live site: https://timeline.enhanceify.co.uk

---
## For the agent (Rob can stop reading here)
An attended session, after the answer: on the Hostinger server, set these values in the live `.env`, then run `php artisan config:clear`:
`MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, `MAIL_HOST=smtp.fastmail.com`, `MAIL_PORT=465`, `MAIL_USERNAME=r@enhanceify.co.uk`, `MAIL_PASSWORD=<the file>`, `MAIL_FROM_ADDRESS=timeline@enhanceify.co.uk`.
This shape is copied from calendar `0010` (done) and enhanceify-V2 `0015`. The `*@enhanceify.co.uk` wildcard alias covers the From address. Do not put the password on a command line. Send one invite to `r@craig.ooo` and ask Rob whether it arrived. Calendar's first mail landed in Junk, so check there too. Then raise a card for the phone answer if it is C or D.

**Relates to** `0004` (the ranking that raised both questions) and `0010` (invite emails, built against this mail setup).

## Comments
**2026-10-07** Condensed for Rob. Question 1 was rewritten. The enhanceify.co.uk zone already sends through Fastmail (calendar `0010`, `docs/DECISIONS.md` 2026-09-12), so options A and B are gone and only the app password is left.

**2026-10-11** **2026-10-11** Phone app: D, make the site installable (Rob). The Fastmail SMTP app password is still owed.
