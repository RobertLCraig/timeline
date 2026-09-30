# Verify moderation end to end against a real Sightengine account

## What I need from you

**Configure a real Sightengine account and prove the moderation path end to end.** Three steps, each
with its own expected result:

1. Create a Sightengine account and put `SIGHTENGINE_API_USER` and `SIGHTENGINE_API_SECRET` in the
   server `.env`, then in Admin, **⚙️ NSFW Settings**, tick "Enable NSFW checks" and click Save
   Settings. Pass: "Settings saved." appears and the toggle reads On. The page never checks the
   credentials, so this step cannot prove them; step 3 does.
2. Upload an ordinary family photo. Pass: it goes live and no flag appears in Admin, **🚩 Content
   Flags**. A flag here means the threshold is set too low to be usable. Missing credentials also
   give no flag, so this pass only counts once step 3 has passed.
3. Upload something the model should score high on. Pass: the event still saves, and the image
   appears in Content Flags with a score. Then click Quarantine. Pass: the flag moves to the
   quarantined filter, and the event's picture is still there. That is criterion #3, which now says
   what `AdminController::reviewFlag` does: it records the decision and touches nothing else.

**Pass** is steps 1 and 2, the flag and score in step 3, plus criterion #4: break the credentials on
purpose and confirm the upload still succeeds with a warning in the log.

**Fail** at step 2 means the threshold is set too low to be usable, which is a config change rather
than a defect. Say the score you saw.

**Why it needs you** Step 1 needs an account signup and a credential that must not enter the
repository, and steps 2 and 3 need your judgement about what an ordinary family photo is. The
failure path is the reason this cannot wait: `UploadController` catches a Sightengine error, logs a
warning and lets the upload through, which is correct behaviour and also the behaviour that makes a
permanently broken integration invisible.

## Why
**Nothing here has ever talked to Sightengine.** The scan path, the flag table and the review queue
have been in the code since February, and there is no evidence in the repository that any of it has
run against the live API. Every test fakes the call.

**What it costs.** A broken integration would look exactly like a working one. `UploadController`
catches a Sightengine error, logs a warning and lets the upload through - the right behaviour, and
also the behaviour that hides a permanently broken integration for as long as nobody reads the log.

**How it came to be this way.** The code was written without an account to test it against, and no
account has been created since.

## Links

**Relates to**
- `0001` - the browser-side check that sits in front of this server scan. It changes what reaches
  the scan, so a change there changes what this verification is measuring.
- `0002` - rewrote the handover's moderation section, and recorded there that quarantine does not
  touch the image. That is why criterion #3 below is expected to fail as written.

## Not this card
The client-side pre-scan, which is card `0001`, linked above. This is about what the server already
does.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN an image is uploaded with checks enabled and credentials set, THE APP SHALL record a
      Sightengine score against it.
- [ ] #2 WHEN an image scores above the configured threshold, THE APP SHALL create an `upload_flags`
      row and show it in Admin, Content Flags.
- [ ] #3 WHEN a flagged upload is quarantined, THE FLAG SHALL move to the quarantined filter with
      the reviewer and time recorded, and the event's `image_url` and the file SHALL be untouched.
      Rewritten 2026-09-30 to what `AdminController::reviewFlag` does: it updates the flag's
      status, reviewer and time, writes an audit row, and nothing else (card `0002`). The old
      wording (null `image_url`, file under `storage/quarantine/`) described code that does not
      exist, so it could only ever fail.
- [ ] #4 WHERE the Sightengine call fails, THE UPLOAD SHALL still succeed and a warning SHALL appear
      in the log, so the fallback is proved rather than assumed.
<!-- AC:END -->

## Tasks
- [ ] Configure credentials and enable checks
- [ ] Run the three uploads above and check the queue
- [ ] Break the credentials deliberately and confirm criterion #4
