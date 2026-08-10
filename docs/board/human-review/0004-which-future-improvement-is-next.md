# Which of the five future improvements is next

## What I need from you
Pick one of the five, or say "none, the app is finished". It decides what gets built after the
moderation work closes, and it needs you because the input is how the family actually uses the
timeline, which is not in the repository.

If you have no strong view, say "1" and it gets built next.

## Why
The handover carries five "Future Improvements" as a flat list with no order and no acceptance,
which is a wishlist rather than a queue. A list like that survives indefinitely because nothing in
it can ever be finished or dropped. Making it one decision turns it into work.

## Options
1. **Email notifications for invite codes.** Codes are currently shared by hand. Cost: needs a
   sending domain and deliverability care, and it is the first outbound mail the app has sent.
2. **Multi-image galleries.** Several uploads per event. Cost: touches the event shape, the upload
   path and the moderation flow that has just been built, so it is the option most likely to
   disturb card 0003's verification.
3. **Real-time comments.** Live commenting on events. Cost: the first feature needing a persistent
   connection or polling, on shared hosting, for a small number of users.
4. **Export and import.** Download or migrate a whole timeline. Cost: modest, and it is the only
   one of the five that reduces risk rather than adding surface, because it is also the backup.
5. **Mobile app.** A React Native wrapper on the existing API. Cost: by far the largest, a second
   build pipeline and two store listings.

## Recommendation
Option 1. Sharing invite codes by hand is the only item on the list that is a live friction rather
than an absent nicety, and it is what limits how many family members are actually on the timeline.
It is also small.

Option 4 is the one I would take next, and there is an argument for taking it first: an export is a
backup, and this app holds family photographs on shared hosting with a SQLite database. If nothing
else on the list gets built, that one still earns itself.

Option 5 is the one to say no to explicitly rather than leave on a list, because a React Native
wrapper is a second product and the API it would wrap is already usable from a phone browser.

## Decided
