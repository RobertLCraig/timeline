# Family Timeline — operational runbook

Practical, battle-tested recipes for adding and managing events on the live
timeline. This is the "how we actually do it" companion to two other docs:

- **AGENTS.md** — the full spec: token abilities, every field, error codes, the
  complete MCP tool list. Read it for reference detail.
- **CLAUDE.md** — the app's architecture and dev environment.
- **C:\Dev\About-Me\Timeline-API.md** — Rob's personal cheat-sheet for agents
  working outside this repo (token loading, conventions). Keep the two in sync.

Everything below is what was used to load Rob's real timeline in June 2026, with
the snags that cost time the first time round.

## Rob's setup (the concrete values)
- **Live site:** `https://timeline.enhanceify.co.uk` (REST base `…/api`, MCP `…/mcp`).
- **Rob's group:** "The Craig Clan", slug `the-craig-clan-rj10vs`. (`demo` is the
  public Johnson Family sample — never write to it.)
- **Token:** stored at `C:\Dev\_secrets\timeline.enhanceify.txt` as shell
  assignments (`TOKEN=""`, `BASE=""`, `SLUG=""`). Needs the `events:write`
  ability to post (plus `groups:read` / `categories:read` to look things up).
  Created in the app under Profile → API Tokens. Shown once, so it lives in the
  secrets file, not in chat or source.

## Convention for Rob's own events
- Medical, mental-health, benefits and admin milestones (PIP, assessments,
  referrals, scan results) go in category **Health** with **`social_visibility:
  private`**, so nothing personal is visible to anyone but Rob. Use **Career**
  for work events (still private).
- Only post events that have actually happened (or firm future appointments Rob
  asks for). The API rejects an `event_date` more than **one year** ahead.
- Write titles and descriptions in Rob's plain voice: no em-dashes, British
  spelling, no filler. See `C:\Dev\About-Me\Writing-Style.md`.

## Two ways in
- **MCP** (native tools, now connected as the "Timeline" server) — best for
  interactive, one-off, or conversational edits. Tools resolve ids for you.
- **REST + curl** — best for bulk loads and scripted runs. The recipes below.

Use MCP for "add this one event" and "fix that event"; use REST for "load these
30 events from a list".

---

## REST recipes (verified working)

### Load the token safely
The secrets file can carry a UTF-8 BOM and Windows CRLF line endings, both of
which break a naive `source` (the BOM corrupts the first variable name, so
`TOKEN` comes back empty). Strip them first, and default `BASE`/`SLUG` in case
they are blank:

```bash
set -a; source <(sed '1s/^\xEF\xBB\xBF//' "C:/Dev/_secrets/timeline.enhanceify.txt" | tr -d '\r'); set +a
BASE="${BASE:-https://timeline.enhanceify.co.uk/api}"
SLUG="${SLUG:-the-craig-clan-rj10vs}"
[ -z "$TOKEN" ] && { echo "TOKEN empty"; exit 1; }
```
Never echo `$TOKEN`.

### Confirm access and categories
```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "$BASE/groups"
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "$BASE/categories"
```
Valid categories: Birth, Move, Anniversary, Graduation, Milestone, Wedding,
Travel, Career, Health, Other. `category` is matched by name, case-insensitive.

### Post one event
```bash
curl -s -w "\nHTTP %{http_code}\n" -X POST "$BASE/groups/$SLUG/events" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"title":"...","event_date":"2026-06-01","category":"Health","social_visibility":"private","description":"..."}'
```
Success is `201` with the new event under `event.id`.

### Bulk load (the reliable pattern)
Apostrophes in descriptions (De Quervain's, couldn't) make inline `-d '...'`
quoting fragile. Instead write one JSON object per line to a temp NDJSON file
(use a real file-writer, not the shell, to dodge quoting), then loop:

```bash
FILE="/path/to/batch.ndjson"
while IFS= read -r line || [ -n "$line" ]; do
  [ -z "$line" ] && continue
  curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/groups/$SLUG/events" \
    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
    --data-binary "$line"
done < "$FILE"
```
Delete the temp file afterwards.

### List events (mind the pagination)
The list endpoint is a Laravel paginator: events are nested under `data` and
there is a default page size, so a plain GET looks like it is missing events.
Pass `per_page` and parse with Python:

```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/groups/$SLUG/events?per_page=200" \
 | python -c "
import sys,json
d=json.load(sys.stdin)
def find(o):
    if isinstance(o,list) and o and isinstance(o[0],dict) and 'event_date' in o[0]: return o
    if isinstance(o,dict):
        for v in o.values():
            r=find(v)
            if r is not None: return r
    return None
for e in sorted(find(d) or [], key=lambda x:(x['event_date'],x['id'])):
    print(e['id'], e['event_date'][:10], e['title'])"
```

### Edit / delete an event
```bash
# Edit (partial — only the fields you send change):
curl -s -X PUT "$BASE/groups/$SLUG/events/<id>" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"title":"Corrected title"}'

# Delete (permanent):
curl -s -o /dev/null -w "%{http_code}\n" -X DELETE "$BASE/groups/$SLUG/events/<id>" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```
Always get the id from the list endpoint first; never guess it.

---

## MCP recipe
The server is registered as the **Timeline** connector (OAuth2, no bearer token
in the call). Typical flow:
1. `whoami` — orient: confirms the user, groups, roles, active group.
2. `list_groups` — get the slug (`the-craig-clan-rj10vs`).
3. `list_categories` — optional, to pick a valid name.
4. `post_timeline_event` — slug + title + `event_date` (YYYY-MM-DD); other fields
   optional and sensibly defaulted.
5. To edit/delete: `list_timeline_events` (search by text/date/category) to find
   the `event_id`, then `update_timeline_event` or `delete_timeline_event`.
   Never guess an `event_id`.

Connect Claude Code if not already: `claude mcp add --transport http timeline
https://timeline.enhanceify.co.uk/mcp` (no `--header` — it discovers OAuth from
the 401 and opens a browser to authorise).

---

## Gotchas that cost time (in one place)
- **BOM/CRLF in the secrets file** silently empties `TOKEN`. Strip both (see the
  load snippet).
- **Pagination wrapper** hides events under `data` with a default page size. Use
  `?per_page=200`.
- **id extraction:** a POST/GET event JSON contains several `"id":` fields
  (`group_id`, the event `id`, `category.id`, `creator.id`). A naive
  `grep -oE '"id":[0-9]+' | tail -1` grabs the **creator** id (Rob = 2), not the
  event id. Parse the JSON properly, or read `event.id` from the POST response.
- **Date cap:** `event_date` must be on or before one year from today, else 422.
- **Limits:** title ≤ 200 chars, description ≤ 5000, 60 event writes/min/token
  (429 if exceeded).
- **403** means the token lacks `events:write` or the user is not a group member.

## Token hygiene
Rob's token lives in `C:\Dev\_secrets\`. Rotate roughly every 180 days, hard
expiry at 2 years. Revoke and recreate from Profile → API Tokens if it ever
leaks (e.g. printed to a log). Keep it out of source control and chat output.
