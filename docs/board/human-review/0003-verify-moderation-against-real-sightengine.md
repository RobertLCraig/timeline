# Verify moderation end to end against a real Sightengine account

## What I need from you
Three steps, each with its own expected result:

1. Create a Sightengine account and put `SIGHTENGINE_API_USER` and `SIGHTENGINE_API_SECRET` in the
   server `.env`, then set `nsfw_checks_enabled` to `1` in Admin settings. Pass: the settings page
   stops reporting the feature as unconfigured.
2. Upload an ordinary family photo. Pass: it goes live and no flag appears in Admin, Flagged
   Uploads. A flag here means the threshold is set too low to be usable.
3. Upload something the model should score high on. Pass: the event still saves, and the image
   appears in Flagged Uploads with a score. Then click Quarantine. Pass: the event's `image_url`
   is nulled and the file has moved to `storage/quarantine/`.

An agent cannot do it: step 1 needs an account signup and a credential that must not enter the
repository, and steps 2 and 3 need a judgement about what a real photo of your family is.

## Why
The scan path, the flag table and the review queue have been in the code since February and there
is no evidence any of it has run against the live API. The one branch that certainly has not been
exercised is the failure path: `UploadController` catches a Sightengine error, logs a warning and
lets the upload through, which is the right behaviour and also the behaviour that makes a
permanently broken integration invisible.

## Not this card
The client-side pre-scan, which is card 0001. This is about what the server already does.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN an image is uploaded with checks enabled and credentials set, THE APP SHALL record a
      Sightengine score against it.
- [ ] #2 WHEN an image scores above the configured threshold, THE APP SHALL create an `upload_flags`
      row and show it in Admin, Flagged Uploads.
- [ ] #3 WHEN a flagged upload is quarantined, THE EVENT'S `image_url` SHALL be null and the file
      SHALL be under `storage/quarantine/`.
- [ ] #4 WHERE the Sightengine call fails, THE UPLOAD SHALL still succeed and a warning SHALL appear
      in the log, so the fallback is proved rather than assumed.
<!-- AC:END -->

## Tasks
- [ ] Configure credentials and enable checks
- [ ] Run the three uploads above and check the queue
- [ ] Break the credentials deliberately and confirm criterion #4
