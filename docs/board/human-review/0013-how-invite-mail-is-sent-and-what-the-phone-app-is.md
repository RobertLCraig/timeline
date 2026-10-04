# How invite emails are sent, and what "the phone app" is

## What I need from you

**Two answers.**

1. Do the app's sign-up emails (the "verify your email" message) reach people from the live site
   today? Yes / no / don't know. Then pick how invite emails are sent: **A** or **B**.
2. For the phone app, pick **C**, **D** or **E**.

---

**On 1.** The app already sends one kind of email: the "verify your email" message on sign-up. If
that arrives in a real inbox from `timeline.enhanceify.co.uk`, invite emails (card `0010`) use the
same setup and nothing more is needed. If it does not, or you do not know, mail is not working on
the live site and one of these has to be set up:

- **A. A mailbox on your own domain at Hostinger.** Already paid for with the hosting. You create
  the mailbox in hPanel and put its SMTP details in the server `.env`. Deliverability is fair, and
  needs the SPF and DKIM records hPanel offers.
- **B. A sending service (Resend, Postmark or similar).** Free at this volume. Better delivery and
  a log of what was sent. Costs a new account and two or three DNS records.

**On 2.** You ranked the phone app last, as a React Native wrapper. That is a second app to build
and keep: a second build pipeline, an Apple developer account at $99 a year, a Google one at $25
once, and two store reviews.

- **C. React Native app, as written.** All the costs above. Ask again for which stores first.
- **D. Make the website installable on a phone instead.** A home-screen icon, full screen, no
  browser bar. No stores, no fees, one small card. It cannot send push notifications on every
  phone.
- **E. Drop it.** The site already works in a phone browser.

**Pass** is both answers in `## Comments`, starting **Decided:**.

**Fail** is no answer on 1. Card `0010` still gets built and tested, but no invite email reaches
anybody until mail works on the live site.

**Why it needs you** Whether mail arrives today is local knowledge: only an inbox shows it, and the
repository has no record of production mail settings. A and B are a cost you carry and an account in
your name. C, D and E turn on how much you want a second product to look after.

## Why
**Two pieces of the ranked work cannot start on reading alone.** Invite emails are only useful if
mail leaves the live site, and nobody has written down whether it does. The phone app was ranked,
but its cost is high enough that its shape needs your say before a card can define "done".

**How it came to be this way.** The ranking on card `0004` set the order. It did not set these two
details, and neither is in the repository.

## Links

**Relates to**
- `0004` - the ranking that made invite emails second and the phone app fifth, which raised both
  questions here.
- `0010` - the invite email card, which is built against whatever answer 1 sets up.

## Options
1. **Mail: A, a Hostinger mailbox on your domain.** Cost: no money. Setting up the mailbox and DNS
   records in hPanel. Fair delivery.
2. **Mail: B, a sending service.** Cost: a new account and DNS records. Better delivery and a send log.
3. **Phone: C, React Native app.** Cost: $99 a year plus $25, a second build, store reviews. Raises
   which stores next.
4. **Phone: D, installable website.** Cost: one small card. No push on every phone.
5. **Phone: E, drop it.** Cost: nothing. No home-screen icon.

## Recommendation
**On 1:** check an inbox first. If sign-up mail arrives, nothing more is needed. If it does not,
take **A**. It is already paid for, and invite emails to family are a few a month.

**On 2:** take **D**. It gives the family the thing they would notice, an icon on the phone, for a
fraction of the cost. C stays possible later.

Line to paste:

    **2026-10-04** **Decided:** 1 - A, a Hostinger mailbox, unless sign-up mail already arrives. 2 - D, make the site installable.

## Comments
