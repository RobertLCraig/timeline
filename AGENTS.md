# Posting to Family Timeline from AI Agents

This guide is for AI agents and scripts (Claude, Ollama, OpenClaw, custom bots)
that need to post events to a Family Timeline group. There are two ways in, both
authenticated by the **same personal access token**:

1. **REST API** — any HTTP client. Universal, no dependencies.
2. **MCP server** — a native `post_timeline_event` tool for MCP-capable agents.

---

## 1. Get a token

A human account holder creates the token in the app:

> **Profile → API Tokens → Create Token**

- Give it a recognisable name (e.g. "Claude desktop").
- Grant only the abilities the agent needs. Defaults: `events:write`,
  `groups:read`, `categories:read`.
- **The plaintext token is shown once.** Copy it immediately; it can't be
  retrieved later, only revoked and recreated.

**Abilities**

| Ability           | Allows                                   |
|-------------------|------------------------------------------|
| `events:write`    | Create and edit timeline events          |
| `events:read`     | Read timeline events                     |
| `groups:read`     | List the user's groups (to find a slug)  |
| `categories:read` | List event categories                    |

**Lifecycle:** rotate tokens roughly every **180 days** (the UI shows a "consider
rotating" badge past that). Tokens hard-expire after **2 years**. Revoke a token
from the same screen to cut off an agent instantly.

The token acts as the user — it can only post to groups that user belongs to,
and all existing visibility/permission rules apply.

---

## 2. REST API

Base URL: `https://<your-timeline-domain>/api` (local dev: `https://timeline.test/api`).

Send the token as a Bearer header. **No CSRF/cookie handling needed** — token
requests are stateless.

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### Find a group slug

```bash
curl https://timeline.test/api/groups \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

### List categories (optional)

```bash
curl https://timeline.test/api/categories \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

### Post an event

```bash
curl -X POST https://timeline.test/api/groups/<group-slug>/events \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
        "title": "Trip to the coast",
        "event_date": "2026-05-30",
        "description": "A long weekend away.",
        "category": "Travel"
      }'
```

**Event fields**

| Field               | Required | Notes                                                                 |
|---------------------|----------|-----------------------------------------------------------------------|
| `title`             | yes      | Max 200 chars.                                                         |
| `event_date`        | yes      | `YYYY-MM-DD`. Must be on or before one year from today.               |
| `description`       | no       | Max 5000 chars.                                                       |
| `category`          | no       | Category **name** (case-insensitive), e.g. `Travel`. Convenience for agents. |
| `category_id`       | no       | Numeric id alternative to `category`.                                |
| `visibility`        | no       | `public` \| `members` (default) \| `private`.                         |
| `social_visibility` | no       | `family` \| `close_friends` \| `friends` \| `acquaintances` \| `public` \| `private`. Defaults from the category if omitted. |
| `image_url`         | no       | Image URL or upload path (max 500).                                  |
| `album_url`         | no       | URL to a full album (max 1000).                                      |

**Success:** `201 Created` with `{ "event": { ... } }`.

**Errors** follow Laravel's shape:
```json
{ "message": "The given data was invalid.", "errors": { "event_date": ["..."] } }
```
- `401` — missing/invalid token.
- `403` — token lacks `events:write`, or the user isn't a member of the group.
- `422` — validation failed (e.g. an unknown `category` returns the valid list).
- `429` — rate limit (max **60 event writes per minute per token**).

---

## 3. MCP server

MCP-capable agents (Claude Desktop, Claude Code) use the hosted Streamable-HTTP
server instead of raw HTTP. **It authenticates via OAuth2, not the Bearer token**
— the agent registers itself, you log in and consent in the browser, and the
agent receives its own scoped token. It exposes three tools:

- `whoami` — the signed-in user, their groups/roles, and active group (call first to orient).
- `post_timeline_event` — create an event.
- `get_timeline_event` — full details of one event by `event_id`.
- `create_group` — create a new group (you become owner).
- `join_group_by_code` — join a group using a shared invite code.
- `set_active_group` — set your default group.
- `list_group_invites` — view a group's invite codes (owner/admin only).
- `update_timeline_event` — edit an existing event by `event_id` (partial; only the fields you pass change).
- `delete_timeline_event` — permanently delete an event by `event_id`.
- `list_timeline_events` — find events (and their ids) in a group by text/date/category, so you can edit or delete a specific one.
- `list_groups` — the user's groups + slugs.
- `list_categories` — global category names; pass a `group` to also see that group's own.
- `create_category` — add a category **for a specific group** (only that group can use it). Returns an existing global/group category with the same name instead of duplicating.
- `list_group_members` — members of a group and their roles.
- `create_group_invite` — generate a shareable join code (group owner/admin only).

Photos & albums: `post_timeline_event` / `update_timeline_event` accept
`image_url` (one photo) and `album_url` (a link to a full album). On update,
pass an empty string for either to remove it.

> **Tip:** sign in to the Family Timeline in your default browser *first*. Then
> the "Connect" step jumps straight to the consent screen instead of asking you
> to log in mid-flow.

### Connect — Claude Desktop

Settings → Connectors → **Add custom connector**:
- **Name:** `Family Timeline`
- **Remote MCP server URL:** `https://<your-timeline-domain>/mcp`
- Leave the OAuth Client ID / Secret **blank** — the server supports dynamic
  client registration, so Claude registers itself automatically.

Click **Add**, then **Connect**; approve the consent screen.

### Connect — Claude Code

```bash
claude mcp add --transport http timeline https://<your-timeline-domain>/mcp
```

No `--header` needed — Claude Code discovers the OAuth server from the 401
challenge and opens a browser to authorize.

Then ask the agent to e.g. *"post a Travel event titled 'Trip to the coast' on
2026-05-30 to my family group."* It will call `list_groups`, optionally
`list_categories`, then `post_timeline_event`.

---

## Notes for implementers

- **Two auth paths, by design:** `/mcp` uses **OAuth2 (Laravel Passport,
  `mcp:use` scope)** for interactive agents; the REST API uses **Sanctum
  personal access tokens** with granular abilities for scripts/curl.
- Every event records a `source` of `web`, `api`, or `mcp` so human and agent
  posts can be distinguished.
- The REST endpoint and the MCP `post_timeline_event` tool create events through
  the same `App\Support\EventCreator` service, so behaviour stays identical.
- Keep tokens out of source control and logs. Prefer environment variables.
