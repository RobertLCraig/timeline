---
not_for_the_loop: needs a third-party account and puts its credentials on the live Hostinger server
---
# Do you want uploaded photos checked for nudity, and will you make the account?

## What I need from you
1. Say whether you still want the timeline to check uploaded photos for nudity. Yes / no.
2. If yes, sign up at https://sightengine.com (free plan). Open the API keys page in its dashboard and save the "API user" and "API secret" values as two lines in `C:\Dev\_secrets\timeline-sightengine.txt`.

**Pass:** the file exists and holds two values.
**My recommendation:** yes. The free plan covers 2,000 checks a month, and family uploads come nowhere near that.
Paste to answer: `**2026-10-07** **Decided:** yes, keep the photo check. The keys are in C:\Dev\_secrets\timeline-sightengine.txt.` or `**2026-10-07** **Decided:** no, drop the photo check.`

## What you need to know
- The check has been in the code since February. It is switched off and has never once talked to Sightengine, the service that scores each photo. Every test fakes the reply.
- If the service ever fails, the upload still goes through and only a line is written to the log. That is the right behaviour, but it also means a broken setup looks exactly like a working one.
- A flagged photo stays live. Quarantine in the admin page only records your decision. It does not hide the picture.
- Free plan: 2,000 checks a month, at most 500 a day. The cheapest paid plan is $29 a month (sightengine.com/pricing, checked 2026-10-07).
- If you say no, the card is dropped and the check stays switched off.

## See it
- Admin, NSFW Settings and Content Flags tabs: https://timeline.enhanceify.co.uk/admin
- Sign-up: https://sightengine.com

---
## For the agent (Rob can stop reading here)
An attended session, after a yes:
1. Put `SIGHTENGINE_API_USER` and `SIGHTENGINE_API_SECRET` in the live server's `.env`. Never put them in the repo, and never pass them on a command line. Then run `php artisan config:clear`. In Admin, NSFW Settings, tick "Enable NSFW checks" and press Save. Pass: "Settings saved." appears and the toggle reads On.
2. Upload an ordinary photo, such as a demo image from `public/assets/demo/`. Pass: no flag appears in Content Flags.
3. Upload an image the model scores high. Pass: the event saves, and a flag with a score appears. Press Quarantine. Pass: the flag moves to the quarantined filter and the picture is still on the event.
4. Break the secret on purpose. Pass: the upload still succeeds, and `Sightengine scan failed` appears in the log. Then restore the secret.

Delete any test events afterwards, because production holds real family data. After a no: move the card to `discarded/`.

<!-- AC:BEGIN -->
- [ ] #1 WHEN an image is uploaded with checks enabled and credentials set, THE APP SHALL record a
      Sightengine score against it. proves: manual
- [ ] #2 WHEN an image scores above the configured threshold, THE APP SHALL create an `upload_flags`
      row and show it in Admin, Content Flags. proves: manual
- [ ] #3 WHEN a flagged upload is quarantined, THE FLAG SHALL move to the quarantined filter with
      the reviewer and time recorded, and the event's `image_url` and the file SHALL be untouched. proves: manual
- [ ] #4 WHERE the Sightengine call fails, THE UPLOAD SHALL still succeed and a warning SHALL appear
      in the log. proves: manual
<!-- AC:END -->

Not this card: the browser-side pre-scan (`0001`, done). The scan code is `UploadController::store()`. Settings are in the `app_settings` table, not `.env`.

## Comments
**2026-09-30** #3 was rewritten to describe what `AdminController::reviewFlag` actually does. It records the decision and touches nothing else (card `0002`).
**2026-10-07** Condensed for Rob. Added the "still wanted?" question and `not_for_the_loop:`.
