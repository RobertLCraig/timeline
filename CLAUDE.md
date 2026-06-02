# Family Timeline — Claude Code Instructions

## Stack
- **Backend**: Laravel 12, PHP 8.2+ (dev) / **8.4 + `ext-sodium` (production — required by Passport, see Deployment)**, SQLite, Laravel Sanctum (SPA cookie auth + API tokens)
- **Frontend**: React 19, React Router v7, Vite 6
- **Agent access**: Laravel MCP server at `/mcp` (OAuth2 via Laravel Passport) + Sanctum personal access tokens for REST. See `AGENTS.md` and "Agent & Programmatic Access" below.
- **Dev environment**: Windows 11, Laravel Herd, Microsoft Edge
- **Production**: Hostinger (`timeline.enhanceify.co.uk`), git-pull deploy — run `bash deploy.sh` on the server after pushing

## Starting the Dev Environment

Run everything with a single composer command (starts Laravel, queue, pail log viewer, and Vite concurrently):
```
composer dev
```

Or start Vite alone (when Laravel is already being served by Herd):
```
npm run dev
```

The app is served by Laravel Herd at `timeline.test` using **compiled assets** from `public/build/`.
**After every frontend change**, rebuild so Herd picks them up:
```
npm run build
```
**Browser**: Microsoft Edge. Use `Ctrl+Shift+R` for a hard refresh after rebuilding.

### Troubleshooting: `timeline.test` shows the IIS welcome page (port 80 conflict)

If `http://timeline.test` returns the blue **"Internet Information Services"** page (or
`curl -I` reports `Server: Microsoft-IIS/10.0`), Windows' IIS has grabbed port 80 and is
starving Herd's nginx. IIS isn't used by this project — disable it (one-time, elevated):
```powershell
Stop-Service W3SVC, WAS -Force
Set-Service  W3SVC -StartupType Disabled   # stops it returning on every boot
```
Then make Herd reclaim the freed port:
```
"C:\Users\r\.config\herd\bin\herd.bat" restart
```
Confirm: `http://timeline.test` should now answer `200` from `nginx` with
`<title>Family Timeline</title>`, and `Get-NetTCPConnection -LocalPort 80 -State Listen`
should show an `nginx` PID.

To re-enable IIS later (elevated): `Set-Service W3SVC -StartupType Automatic; Start-Service W3SVC`.

## PHP / Artisan Commands

Use `php artisan` for all Artisan commands. On **Windows with Laravel Herd**, `php` may not be
on the PATH — substitute the full binary path:
```
"C:\Users\r\.config\herd\bin\php.bat"
```

Run migrations:
```
php artisan migrate --force
```

Run tests:
```
composer test
```

## Deployment (Hostinger)

Git-pull based. Push to `origin/main`, then on the server run the bundled
script from the app dir (`~/domains/timeline.enhanceify.co.uk/laravel`):
```
bash deploy.sh   # git pull, composer install --no-dev, migrate, passport:keys (first run), cache
```
SSH: `ssh -p 65002 u408983312@141.136.33.219`. Frontend assets are committed
(`public/build/`) because the server has no Node — always `npm run build` and
commit before pushing.

> **⚠ Production holds real user data — do not clobber it.** Deploys run only
> additive `migrate --force`. On production **never** run `migrate:fresh`,
> `migrate:rollback`, `db:wipe`, or `db:seed`. The seeders (`DatabaseSeeder` /
> `DemoSeeder`) are **dev/local fixtures**: they create the default super-admin
> (`admin@family.com` / `admin123`) and add every user to the demo group, so the
> admin + demo block is gated to non-production. Keep new migrations additive
> (add nullable columns / new tables); never `drop`/`truncate` data tables in a
> migration that will run on prod. The default admin is **local only** — it must
> not exist with known credentials on production.

> **Production PHP must be 8.4 with `ext-sodium` enabled** (hPanel → PHP
> Configuration). Laravel Passport pulls Symfony components that require PHP ≥
> 8.4, and Passport needs sodium. `composer.json` pins `config.platform.php` to
> 8.4 so the autoloader's platform check matches the web SAPI. After `route:cache`,
> verify the MCP route survived: `curl -s -o /dev/null -w '%{http_code}' -X POST
> <domain>/mcp` should return **401** (not 404).

## Project Structure

```
c:\Dev\timeline\
├── app/
│   ├── Http/Controllers/     # AuthController, GroupController, EventController,
│   │                         # CategoryController, VisibilityController,
│   │                         # UploadController, AdminController, TokenController
│   ├── Http/Middleware/      # EnsureGroupRole, EnsureSuperAdmin, ResolveGroup,
│   │                         # AuthenticateMcp (OAuth guard for /mcp)
│   ├── Mcp/                  # MCP server (agent access)
│   │   ├── Servers/TimelineServer.php   # registers the 15 tools
│   │   └── Tools/            # one class per tool (post/update/delete/list/… events,
│   │                         # groups, invites, categories) — see AGENTS.md
│   ├── Support/
│   │   └── EventCreator.php  # shared event create/update logic (REST + MCP)
│   └── Models/
├── database/
│   ├── migrations/
│   └── database.sqlite       # SQLite database file
├── AGENTS.md                 # how agents authenticate & post (tokens + MCP)
├── deploy.sh                 # server-side deploy (pull, composer, migrate, keys, cache)
├── resources/js/
│   ├── App.jsx               # Router setup
│   ├── main.jsx              # Entry point
│   ├── context/
│   │   └── AuthContext.jsx   # Auth state + setActiveGroup helper
│   ├── lib/
│   │   └── api.js            # API client (cookie auth, XSRF header)
│   ├── components/
│   │   ├── Navbar.jsx
│   │   ├── ProtectedRoute.jsx
│   │   └── views/             # Alternative timeline views (see "Timeline Views")
│   │       ├── ZoomableTimelineView.jsx
│   │       ├── CalendarHeatmapView.jsx
│   │       ├── MonthCalendarView.jsx
│   │       ├── PhotoMosaicView.jsx
│   │       ├── EventModal.jsx   # Shared event-detail dialog for the above views
│   │       └── views.css        # Shared styles for all view components + modal
│   └── pages/
│       ├── GroupTimeline.jsx  # View switcher + vertical timeline + YearMapSlider
│       ├── GroupTimeline.css
│       ├── EventForm.jsx
│       ├── GroupSettings.jsx
│       ├── Dashboard.jsx      # Redirects to active group or join/create prompt
│       ├── Login.jsx
│       ├── Register.jsx
│       ├── CreateGroup.jsx
│       ├── Profile.jsx
│       ├── CategoryVisibility.jsx
│       ├── GroupVisibility.jsx
│       ├── AdminPanel.jsx
│       ├── Profile.jsx       # incl. "API Tokens" section (create/revoke PATs)
│       └── Landing.jsx
└── routes/
    ├── api.php               # REST API routes (Sanctum)
    ├── web.php               # SPA catch-all + named `login` + Google OAuth callback
    └── ai.php                # MCP server + OAuth discovery routes (Mcp::oauthRoutes)
```

## Key Architecture Decisions

### Authentication
- **Sanctum SPA cookie auth** — session stored in an HttpOnly cookie; no tokens in localStorage
- Flow: app mount → `GET /sanctum/csrf-cookie` → sets `XSRF-TOKEN` cookie → all mutations include `X-XSRF-TOKEN` header
- `credentials: 'include'` is set on every fetch in `api.js`; Sanctum's `statefulApi()` middleware is registered in `bootstrap/app.php`
- `SANCTUM_STATEFUL_DOMAINS` in `.env` must match the browser-visible domain (e.g. `timeline.test`)
- On public routes (no `auth:sanctum` middleware): use `Auth::guard('sanctum')->user()` to optionally read the session — NOT `$request->user()`

### Agent & Programmatic Access (API tokens + MCP)
Two separate auth paths let AI agents/scripts post events as a real user. Full
guide: `AGENTS.md`.

- **REST + Sanctum personal access tokens** (`/api/...`): users mint scoped
  tokens in Profile → API Tokens (`TokenController`, abilities `events:write` /
  `events:read` / `groups:read` / `categories:read`, 2-year expiry, 180-day
  rotation nudge). Sent as `Authorization: Bearer <token>`. Event writes are
  gated by `ability:events:write` + a 60/min per-token throttle (`events-write`
  limiter in `bootstrap/app.php`).
- **MCP server over OAuth2** (`/mcp`, `routes/ai.php`): a Laravel MCP server
  (15 tools) for Claude Desktop/Code. Auth is **Laravel Passport** (the `api`
  guard, scope `mcp:use`) via dynamic client registration + browser consent —
  NOT Sanctum. The custom `AuthenticateMcp` middleware returns (not throws) 401
  so laravel/mcp's `WWW-Authenticate` discovery header survives. Passport's
  consent view is `resources/views/oauth/authorize.blade.php`; scope + view are
  registered in `AppServiceProvider::boot()` (so they survive `route:cache`).
  The `User` model keeps **only Sanctum's** `HasApiTokens` — Passport's
  `TokenGuard` works because Sanctum's `withAccessToken()` is untyped and
  `tokenCan()` duck-types on `->can()`.
- **Shared logic**: both paths create/update events through
  `App\Support\EventCreator` (category-by-name resolution scoped to the group,
  social-visibility resolution, `source` stamp). Every event records
  `source` = `web` | `api` | `mcp`.
- **Authorization rule** (enforced in both REST controllers and MCP tools):
  edit/delete an event ⇒ super admin OR (current group member AND (event creator
  OR group admin/owner)). Group-admin actions (invites, member mgmt) ⇒
  owner/admin. Covered by `McpAuthorizationTest` + `CategoryScopeTest`.

### Active Group
- Users have `active_group_id` on the `users` table — set when first joining/creating a group
- `Dashboard.jsx` auto-redirects to the active group's timeline; shows join/create UI if no groups
- `Login.jsx` checks `data.user.groups` post-login and redirects to the active group slug
- `AuthContext.jsx` exposes a `setActiveGroup` helper

### Social Visibility System
Tiers (numeric rank, higher = broader audience):
| Tier | Value |
|------|-------|
| private | 0 |
| family | 1 |
| close_friends | 2 |
| friends | 3 |
| acquaintances | 4 |
| public | 5 |

- Group timeline filters by BOTH legacy visibility (`public`/`members`/`private`) AND social tier
- Default tier for groups/events: `friends` unless customised
- Per-user category defaults: `category_visibility_defaults` table (`user_id`, `category_id`, `visibility_tier`)
- Per-user group tier: `user_group_visibility` table (`user_id`, `group_id`, `visibility_tier`)

### Timeline Views (view switcher)
`GroupTimeline.jsx` renders a segmented **view switcher** in the filter bar that toggles
how a group's events are displayed. All views consume the same `displayedEvents`, so the
category filter (left sidebar) carries across every view.

| Key | View | Component | Notes |
|-----|------|-----------|-------|
| `timeline` | Vertical cards | (inline in `GroupTimeline.jsx`) | The original view; the only one with sort + YearMapSlider |
| `zoom` | Zoomable horizontal axis | `ZoomableTimelineView.jsx` | `+`/`−` zoom (8–800 px/yr), nice-stepped year ticks, greedy lane-stacking to avoid label overlap |
| `heatmap` | Year × month density grid | `CalendarHeatmapView.jsx` | Cell shade scales with event count; click a cell → that month's events listed below |
| `calendar` | Month grid | `MonthCalendarView.jsx` | Prev/next nav + "Latest" jump; defaults to the most recent month with events |
| `photos` | Image masonry by year | `PhotoMosaicView.jsx` | Only events with `image_url`; click a tile → modal |

- **Persistence**: the chosen view is saved per group in `localStorage` under `tl-view:<slug>`
  and restored on navigation (see the `slug` effect in `GroupTimeline.jsx`).
- **Shared modal**: the non-timeline views call `onSelect(event)`, which opens the page-level
  `EventModal` (image, badges, description, album link, Edit/Delete, `Esc` to close).
- **Timeline-only behaviour**: the YearMapSlider, scroll-sync, scroll-to-year, and year-range
  filter are all gated to `view === 'timeline'`; the layout adds `.no-right` (two columns)
  for the other views.
- New view? Add it to `VIEW_OPTIONS`, render it in the view switch, and have it accept
  `{ events, onSelect }`. Put styles in `views.css` using the global design tokens.

### YearMapSlider (GroupTimeline.jsx)
- Vertical minimap-style year range selector (VSCode-inspired)
- Desktop: right-side sticky sidebar, full viewport height (`calc(100vh - 80px)`)
- Mobile (<900px): horizontal strip above timeline (height: 140px)
- Default window on load: first 10% of the event year span
- Three drag modes: `window` (move), `top` (resize start), `bottom` (resize end)

## Database Schema (Key Tables)

| Table | Notable columns |
|-------|----------------|
| `users` | `active_group_id`, `platform_role` (super_admin) |
| `groups` | `slug`, `visibility`, `invite_code` |
| `group_members` | `user_id`, `group_id`, `role` (owner/admin/member) |
| `group_invites` | `code`, `group_id`, `created_by`, `max_uses`, `current_uses`, `expires_at` |
| `events` | `group_id`, `category_id`, `event_date`, `visibility`, `social_visibility`, `image_url`, `album_url`, `source` (web/api/mcp) |
| `event_categories` | `name`, `icon`, `color`, **`group_id`** (NULL = global/shared; set = that group only) |
| `category_visibility_defaults` | `user_id`, `category_id`, `visibility_tier` |
| `user_group_visibility` | `user_id`, `group_id`, `visibility_tier` |
| `personal_access_tokens` | Sanctum API tokens (`abilities`, `expires_at`) |
| `oauth_clients`, `oauth_access_tokens`, `oauth_auth_codes`, `oauth_refresh_tokens` | Passport (MCP OAuth) |

## Coding Conventions

- Controllers are thin — validation/business logic mostly in the controller. The one shared service is `App\Support\EventCreator` (event create/update), reused by REST controllers and MCP tools so they stay in lockstep
- MCP tools (`app/Mcp/Tools/`) and REST endpoints must enforce the **same** authorization (see "Agent & Programmatic Access"). When adding a mutating tool, mirror the REST ownership/membership checks and add a test to `McpAuthorizationTest`
- React components use hooks only (no class components)
- API responses follow `{ data, meta }` pattern for paginated results; flat object for single resources
- CSS is per-page (e.g. `GroupTimeline.css`) — no CSS modules or Tailwind
- Design tokens live in a global CSS variables file; use `var(--...)` not hardcoded values
