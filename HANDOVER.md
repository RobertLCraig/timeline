# Family Timeline — Technical Handover

**Category:** site

This document is the technical reference for anyone picking up development on the Family Timeline project.

---

## Architecture Overview

A **Laravel 12 + React 19 monolith** — one repository, one deployment unit.

- Laravel serves the app shell (`resources/views/app.blade.php`) and all API routes under `/api/`.
- React handles all UI via client-side routing (React Router v7).
- Assets are compiled by Vite 6 into `public/build/` (via `npm run build`).
- When `npm run dev` is running, Laravel proxies to the Vite dev server via the `public/hot` file.

---

## Directory Structure

```
c:\Dev\timeline\
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php         # register, login, logout, me
│   │   ├── GroupController.php        # CRUD + join/leave
│   │   ├── EventController.php        # CRUD for timeline events
│   │   ├── CategoryController.php     # event categories
│   │   ├── VisibilityController.php   # social visibility settings
│   │   ├── UploadController.php       # image upload handling
│   │   └── AdminController.php        # super-admin: users, referral codes
│   └── Models/
│       ├── User.php
│       ├── Group.php
│       ├── GroupMember.php
│       ├── Event.php
│       ├── EventCategory.php
│       ├── ReferralCode.php
│       └── (visibility tables)
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   ├── DatabaseSeeder.php         # categories + admin user + DemoSeeder
│   │   └── DemoSeeder.php             # "The Johnson Family" — 70 events
│   └── database.sqlite
├── resources/js/
│   ├── App.jsx                        # router setup
│   ├── main.jsx                       # React entry point (StrictMode)
│   ├── context/AuthContext.jsx        # auth state + setActiveGroup helper
│   ├── lib/api.js                     # fetch wrapper (cookie auth, XSRF header, 401 handling)
│   ├── components/
│   │   ├── Navbar.jsx
│   │   └── ProtectedRoute.jsx
│   └── pages/
│       ├── GroupTimeline.jsx          # main timeline view + YearMapSlider component
│       ├── GroupTimeline.css
│       ├── EventForm.jsx
│       ├── GroupSettings.jsx
│       ├── Dashboard.jsx              # redirects to active group or join/create
│       ├── Login.jsx
│       ├── Register.jsx
│       ├── CreateGroup.jsx
│       ├── Profile.jsx
│       ├── CategoryVisibility.jsx
│       ├── GroupVisibility.jsx
│       ├── AdminPanel.jsx
│       └── Landing.jsx
└── routes/api.php                     # all API routes
```

---

## Backend

### Authentication

- **Laravel Sanctum SPA mode** — session-based, stored in an HttpOnly cookie. No tokens in `localStorage`.
- **CSRF flow**: On app mount, the React app calls `GET /sanctum/csrf-cookie`. Sanctum sets a readable `XSRF-TOKEN` cookie. All mutating requests (`POST`, `PUT`, `DELETE`) include an `X-XSRF-TOKEN` header (read from that cookie).
- `credentials: 'include'` is set on every `fetch` in `api.js` so the session cookie is always sent.
- `statefulApi()` is registered in `bootstrap/app.php`, which adds session, CSRF, and cookie middleware to the API.
- `SANCTUM_STATEFUL_DOMAINS` in `.env` must list the domain(s) the browser uses to access the app (without protocol). Example: `timeline.test,localhost,localhost:5173`.
- All authenticated routes use `auth:sanctum` middleware (works identically with session cookies).
- **API tokens**: the same `auth:sanctum` routes also accept Sanctum personal access tokens (`Authorization: Bearer …`) for scripts/agents — created in Profile → API Tokens with scoped abilities. CSRF is skipped for token (non-stateful) requests.
- **MCP / agent access**: a separate Laravel MCP server at `/mcp` (`routes/ai.php`, 15 tools) authenticates AI agents via **Laravel Passport OAuth2** (the `api` guard, scope `mcp:use`) — independent of Sanctum. See `AGENTS.md` and CLAUDE.md → "Agent & Programmatic Access". Both paths funnel event writes through `App\Support\EventCreator` and enforce the same ownership/membership authorization.
- **Optional auth pattern**: Public routes that *optionally* read the session use `Auth::guard('sanctum')->user()` — NOT `$request->user()` — to avoid a 401 when unauthenticated.

### Key Models & Tables

| Model | Table | Notable columns |
|---|---|---|
| `User` | `users` | `platform_role` (user/super_admin), `active_group_id` |
| `Group` | `groups` | `slug`, `visibility`, `invite_code` |
| `GroupMember` | `group_members` | `user_id`, `group_id`, `role` (owner/admin/member) |
| `Event` | `events` | `group_id`, `category_id`, `event_date`, `visibility`, `social_visibility`, `image_url`, `image_urls`, `album_url` |
| `EventCategory` | `event_categories` | `name`, `icon`, `color` |
| `ReferralCode` | `referral_codes` | `code`, `max_uses`, `current_uses`, `expires_at` |
| — | `category_visibility_defaults` | `user_id`, `category_id`, `visibility_tier` |
| — | `user_group_visibility` | `user_id`, `group_id`, `visibility_tier` |

### Event Visibility — Two Layers

Events carry **two independent visibility fields**:

**1. Legacy visibility** (`public` / `members` / `private`):

| Value | Who sees it |
|---|---|
| `public` | Everyone (including unauthenticated) |
| `members` | Logged-in group members only |
| `private` | Creator + group admin/owner only |

**2. Social visibility tier** (numeric, higher = broader audience):

| Tier | Value |
|---|---|
| `private` | 0 |
| `family` | 1 |
| `close_friends` | 2 |
| `friends` | 3 (default) |
| `acquaintances` | 4 |
| `public` | 5 |

Both filters are applied when a group timeline is fetched. Per-user category defaults are stored in `category_visibility_defaults`; per-user group tier overrides in `user_group_visibility`.

### Active Group

- Users have `active_group_id` on the `users` table — set on first group join/create.
- `Dashboard.jsx` reads this and auto-redirects to the active group's slug.
- `AuthContext.jsx` exposes a `setActiveGroup(group)` helper that updates the value.

### Registration

- Registration is open; a **referral code** is optional (nullable).
- If provided, the code is validated against `referral_codes` and its `current_uses` is incremented.
- Super-admins generate and manage referral codes in the Admin Panel.

### Group Membership

- Groups generate a random **invite code** (`groups.invite_code`).
- Any registered user can join a group by submitting the invite code at `/g/{slug}`.
- The group timeline is visible to unauthenticated visitors if the group's `visibility` is `public`.

---

## Frontend

### Routing (App.jsx)

| Path | Component | Auth required |
|---|---|---|
| `/` | `Landing` | No |
| `/login` | `Login` | No |
| `/register` | `Register` | No |
| `/dashboard` | `Dashboard` | Yes |
| `/g/:slug` | `GroupTimeline` | No (public groups visible) |
| `/g/:slug/events/new` | `EventForm` | Yes |
| `/g/:slug/events/:id/edit` | `EventForm` | Yes |
| `/g/:slug/settings` | `GroupSettings` | Yes (admin/owner) |
| `/profile` | `Profile` | Yes |
| `/admin` | `AdminPanel` | Yes (super_admin) |

### Auth State (AuthContext.jsx)

Provides: `user`, `isAuthenticated`, `isLoading`, `login()`, `logout()`, `refreshUser()`, `setActiveGroup()`.

On mount, `AuthContext` calls `GET /sanctum/csrf-cookie` (sets the XSRF cookie), then `GET /api/auth/me` (restores session if the HttpOnly cookie is still valid). No localStorage is used.

### API Client (api.js)

Thin wrapper around `fetch`:
- Prepends `/api` to all paths
- Sends `credentials: 'include'` on every request (so the session cookie is always sent)
- Reads `XSRF-TOKEN` from the browser cookie and injects it as `X-XSRF-TOKEN` on mutations
- On 401, emits `auth:logout` event — `AuthContext` sets `user` to null in response

### YearMapSlider (GroupTimeline.jsx)

A VSCode-minimap-style year navigation component embedded in the right sidebar of the group timeline.

**Modes:**
- **Scroll mode** (default): Dragging the slider window scrolls the timeline to those years. The slider auto-syncs its position as you scroll the page. A toggle button switches to Filter mode.
- **Filter mode**: Events outside the selected year range are hidden. A "Reset" button restores the full range.

**Direction:** When sort order is "Newest First" (`sort='desc'`), the slider reverses — newest year at the top, oldest at the bottom.

**Layout:** 3-column CSS grid (`210px 1fr 210px`) — category sidebar | timeline | year-range sidebar. Both sidebars are sticky and full viewport height. Collapses to single column on screens < 900px.

**Loop prevention:** `yearRangeSourceRef` tracks whether a `yearRange` state change originated from the slider (`'slider'`) or from the page scroll listener (`'scroll'`). Only `'slider'` changes trigger `scrollTo()`.

---

## Event Categories (seeded)

| Category | Icon | Colour |
|---|---|---|
| Birth | 👶 | #ec4899 |
| Move | 🏠 | #8b5cf6 |
| Anniversary | 💍 | #f59e0b |
| Graduation | 🎓 | #3b82f6 |
| Milestone | 🏆 | #10b981 |
| Wedding | 💒 | #f43f5e |
| Travel | ✈️ | #06b6d4 |
| Career | 💼 | #6366f1 |
| Health | 🏥 | #14b8a6 |
| Other | 📌 | #64748b |

---

## Demo Data

`DemoSeeder.php` seeds **"The Johnson Family"** — a fictional family with 70 events spanning 1980–2024, covering all 10 categories. 46 events have matching images in `public/assets/demo/`. The seeder is idempotent (`firstOrCreate`) and always re-applies image URLs from the `$imageMap`.

To re-run the demo seeder alone:
```bash
php artisan db:seed --class=DemoSeeder
```

---

## Build Pipeline

```bash
# Development — starts Laravel (via Herd), queue worker, log viewer (pail), and Vite HMR
composer dev

# Production build — also removes stale public/hot before building
npm run build
```

> **Stale `public/hot` issue**: If `npm run dev` is killed without a clean shutdown, `public/hot` is left behind. Laravel reads this file to decide whether to load assets from the Vite dev server. If the server isn't running, the page goes blank. `npm run build` runs a `prebuild` script that deletes this file automatically.

---

## Content Moderation

Shipped in February. The server scans every upload, two admin tabs control and review it, and the
scan is off out of the box because the setting seeds to `0` and there are no credentials.

### Server-side scan — `app/Http/Controllers/UploadController.php`

`POST /api/upload` saves the file to `public/uploads/` first, then scans it in the same request —
there is no queued job. `scanEnabled()` requires **both** the `nsfw_checks_enabled` setting at `1`
and `config('services.sightengine.user')` and `.secret` to be set, so an unconfigured install skips
the scan silently.

The scan is one multipart call to `https://api.sightengine.com/1.0/check.json` with `models=nudity-2.0`
(no publicly reachable URL needed). `topScore()` takes the highest of `sexual_activity`,
`sexual_display`, `erotica` and `very_suggestive` from the response and compares it to the
`nudity_threshold` setting. At or above it, an `upload_flags` row is written with status `pending`.

The response is `{ url, filename, flagged, flag_id }`. **A flagged upload still succeeds** — the file
is served and the event keeps it; the flag is only a review item. If Sightengine errors or is
unreachable the exception is caught, a `Sightengine scan failed` warning is logged, and the upload
succeeds unflagged.

Sightengine was chosen over self-hosted NudeNet because Hostinger shared hosting has no Python
runtime or process manager. On a VPS the call in `callSightengine()` can point at a self-hosted
service instead; nothing else changes.

### Settings table — `app_settings`

Key/value store. Migration `database/migrations/2026_02_25_000700_create_app_settings_table.php`,
model `app/Models/AppSetting.php` (`AppSetting::get()` / `::set()`, **no cache** — every read is a
query). The migration seeds the two rows below; both are read straight from the table, not from the
environment.

| Setting | Seeded | Meaning |
|---|---|---|
| `nsfw_checks_enabled` | `0` | Master switch for the **server-side** scan |
| `nudity_threshold` | `0.6` | Score at or above which an upload is flagged |

Super-admins edit them in the **⚙️ NSFW Settings** tab (`NsfwSettingsTab` in
`resources/js/pages/AdminPanel.jsx`) over `GET`/`PUT /api/admin/settings`
(`AdminController::getSettings` / `updateSettings`). Writes are validated against a fixed key list
and recorded in `audit_logs` as `admin.settings_updated`.

### Flag table — `upload_flags`

Migration `database/migrations/2026_02_25_000800_create_upload_flags_table.php`, model
`app/Models/UploadFlag.php`. Columns: `filename`, `url`, `uploader_user_id`, `scores` (the raw
Sightengine nudity block, cast to array), `top_score`, `status` (`pending`/`approved`/`quarantined`),
`reviewed_by`, `reviewed_at`, `created_at`. There is no `updated_at`.

### Admin review queue

`GET /api/admin/upload-flags?status=…` (`AdminController::uploadFlags`, paginated 20 with uploader
and reviewer eager-loaded) and `PUT /api/admin/upload-flags/{id}` (`AdminController::reviewFlag`).
The UI is the **🚩 Content Flags** tab (`ContentFlagsTab` in `resources/js/pages/AdminPanel.jsx`):
pending/approved/quarantined filter, image preview, score, uploader, and Approve / Quarantine
buttons. Each decision writes an `upload_flag.approved` / `upload_flag.quarantined` audit log entry.

> **Both decisions are bookkeeping only.** `reviewFlag()` sets `status`, `reviewed_by` and
> `reviewed_at` and nothing else. Quarantine does **not** null the event's `image_url`, does not
> delete the file, and does not move it to `storage/quarantine/` — the image stays live at its
> `public/uploads/` URL. Nobody is emailed either way.

The evidence for the server scan and its threshold is `tests/Feature/UploadScanTest.php`, which
fakes Sightengine: `test_an_allowed_upload_is_still_scanned_server_side` proves the call goes out,
and `test_an_image_over_the_threshold_is_flagged_for_review` proves a score at or above `0.6` writes
an `upload_flags` row. Only the failure path — the `catch` in `UploadController::store` — is
unobserved. None of it has been run against a real Sightengine account; that check is a card on the
board, not a code change.

### Environment

Only two variables are read, both via `config/services.php` → `services.sightengine`:

```
SIGHTENGINE_API_USER=
SIGHTENGINE_API_SECRET=
```

`.env.example` also carries `SIGHTENGINE_NUDITY_THRESHOLD` and `NSFW_CHECKS_ENABLED`. **Nothing reads
them** — those two controls live in `app_settings` (above) and setting them in `.env` does nothing.

### Not settled: the client-side pre-scan

The browser pre-scan (`resources/js/lib/nsfwScan.js`, called from `EventForm.jsx`) is built but has
not passed review. It is card `0001` in [docs/board/human-review/](docs/board/human-review/) — read
the card for its design and its open findings rather than trusting a summary here.

---

## Work in flight

The queue is [docs/board/todo/](docs/board/todo/) and what waits on a person is
[docs/board/human-review/](docs/board/human-review/).

The five improvements this section used to list are one card, `0004`, because a flat wishlist with
no order and no acceptance is not a queue: nothing in it can be finished or dropped, so it survives
forever. Picking one turns it into work.

Content moderation is described above as what it is, not as a plan. Two things about it are open and
both are cards, not paragraphs: the client-side pre-scan is card `0001`, and proving the server scan
against a real Sightengine account is card `0003`.
