# Client-side NSFW pre-scan before upload

## Why
Four of the five parts of the Phase 4 moderation design have shipped: the Sightengine scan in
`UploadController`, the `app_settings` and `upload_flags` tables, the admin review routes, and the
Flagged Uploads tab in `AdminPanel.jsx`. The client-side pre-scan is the one part that has not:
`@tensorflow-models/nsfwjs` appears nowhere in `package.json` or `resources/js`.

Without it every image reaches the server and burns a Sightengine call, and the free tier is 500
images a month. The uploader also gets no feedback until after the upload completes.

## Not this card
Changing the server-side scan, the threshold setting, or the review queue. Those are built and this
card sits in front of them, not over them.

## Acceptance
<!-- AC:BEGIN -->
- [ ] WHEN a user selects an image whose `Porn`, `Hentai` or `Sexy` score exceeds 0.7, THE APP SHALL
      block the upload in the browser and say why, before any request is sent.
- [ ] WHEN a user selects an image scoring below the threshold, THE APP SHALL upload it normally and
      the server-side scan SHALL still run, so the client check is a filter and never the only one.
- [ ] WHERE the model fails to load, THE APP SHALL allow the upload and fall through to the server
      scan rather than blocking the user out of a working feature.
<!-- AC:END -->

## Tasks
- [ ] Add `@tensorflow-models/nsfwjs` and load the model lazily, not on app boot
- [ ] Hook the classification into the upload component before the request
- [ ] Prove the fall-through path by breaking the model load deliberately

## Plan
The threshold 0.7 is the figure the Phase 4 design named and the server already reads its own
threshold from `app_settings`. Keep the client figure a named constant rather than reaching for the
setting: the server setting is authoritative and a client that fetched it would be a second copy of
the rule.
