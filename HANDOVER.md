# Family Timeline — Technical Handover

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
│   ├── lib/api.js                     # fetch wrapper (Bearer token, 401 handling)
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

- **Laravel Sanctum** — token-based (not cookie). Tokens stored in `localStorage` on the client.
- All authenticated routes use `auth:sanctum` middleware.
- **Optional auth pattern**: Public routes that *optionally* read the token use `Auth::guard('sanctum')->user()` — NOT `$request->user()` — to avoid a 401 when no token is present.

### Key Models & Tables

| Model | Table | Notable columns |
|---|---|---|
| `User` | `users` | `platform_role` (user/super_admin), `active_group_id` |
| `Group` | `groups` | `slug`, `visibility`, `invite_code` |
| `GroupMember` | `group_members` | `user_id`, `group_id`, `role` (owner/admin/member) |
| `Event` | `events` | `group_id`, `category_id`, `event_date`, `visibility`, `social_visibility`, `image_url`, `album_url` |
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

Tokens are stored in `localStorage` under `authToken` and attached automatically by `api.js`.

### API Client (api.js)

Thin wrapper around `fetch`:
- Prepends `/api` to all paths
- Injects `Authorization: Bearer <token>` header
- On 401, clears token and emits a `auth:logout` event that `AuthContext` listens to

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

## Future Improvements

- **Email notifications** — invite codes sent by email rather than shared manually.
- **Real-time comments** — live commenting on events.
- **Multi-image galleries** — multiple uploads per event.
- **Export / import** — download or migrate an entire timeline.
- **Mobile app** — React Native wrapper using the existing API.
