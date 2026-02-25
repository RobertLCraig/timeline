# Family Timeline — Claude Code Instructions

## Stack
- **Backend**: Laravel 12, PHP 8.2+, SQLite, Laravel Sanctum (token auth)
- **Frontend**: React 19, React Router v7, Vite 6
- **Dev environment**: Windows 11, Laravel Herd, Microsoft Edge

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

## PHP / Artisan Commands

PHP binary (via Herd):
```
"C:\Users\r\.config\herd\bin\php.bat"
```

Run migrations:
```
"C:\Users\r\.config\herd\bin\php.bat" "C:\Dev\timeline\artisan" migrate --force
```

Run tests:
```
composer test
```

## Project Structure

```
c:\Dev\timeline\
├── app/
│   ├── Http/Controllers/     # AuthController, GroupController, EventController,
│   │                         # CategoryController, VisibilityController,
│   │                         # UploadController, AdminController
│   └── Models/
├── database/
│   ├── migrations/
│   └── database.sqlite       # SQLite database file
├── resources/js/
│   ├── App.jsx               # Router setup
│   ├── main.jsx              # Entry point
│   ├── context/
│   │   └── AuthContext.jsx   # Auth state + setActiveGroup helper
│   ├── lib/
│   │   └── api.js            # API client (Bearer token support)
│   ├── components/
│   │   ├── Navbar.jsx
│   │   └── ProtectedRoute.jsx
│   └── pages/
│       ├── GroupTimeline.jsx  # Main timeline view + YearMapSlider component
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
│       └── Landing.jsx
└── routes/
    └── api.php               # All API routes
```

## Key Architecture Decisions

### Authentication
- **Sanctum token auth** — tokens stored in localStorage, sent as `Bearer` header
- On public routes (no `auth:sanctum` middleware): use `Auth::guard('sanctum')->user()` to optionally read the token — NOT `$request->user()`
- `resources/js/lib/api.js` attaches the token automatically to all requests

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

### YearMapSlider (GroupTimeline.jsx)
- Vertical minimap-style year range selector (VSCode-inspired)
- Desktop: right-side sticky sidebar, full viewport height (`calc(100vh - 80px)`)
- Mobile (<900px): horizontal strip above timeline (height: 140px)
- Default window on load: first 10% of the event year span
- Three drag modes: `window` (move), `top` (resize start), `bottom` (resize end)

## Database Schema (Key Tables)

| Table | Notable columns |
|-------|----------------|
| `users` | `active_group_id` |
| `groups` | `slug`, `visibility`, `invite_code` |
| `group_members` | `user_id`, `group_id`, `role` (owner/admin/member) |
| `events` | `group_id`, `category_id`, `event_date`, `visibility`, `social_visibility` |
| `event_categories` | `name`, `icon`, `color` |
| `category_visibility_defaults` | `user_id`, `category_id`, `visibility_tier` |
| `user_group_visibility` | `user_id`, `group_id`, `visibility_tier` |

## Coding Conventions

- Controllers are thin — validation and business logic in the controller, no separate service layer yet
- React components use hooks only (no class components)
- API responses follow `{ data, meta }` pattern for paginated results; flat object for single resources
- CSS is per-page (e.g. `GroupTimeline.css`) — no CSS modules or Tailwind
- Design tokens live in a global CSS variables file; use `var(--...)` not hardcoded values
