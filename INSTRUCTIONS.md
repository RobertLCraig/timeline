# Family Timeline — Setup & Usage Guide

---

## Development Setup

### Prerequisites

- PHP 8.2+ (via [Laravel Herd](https://herd.laravel.com/) on Windows/macOS, or system PHP on Linux)
- Node.js 18+ and npm
- Composer

> **Laravel Herd on Windows**: Herd provides its own PHP binary. If `php` is not on your PATH,
> replace `php` with `"C:\Users\<you>\.config\herd\bin\php.bat"` in all commands below.

### First-time setup

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Create environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations and seed demo data
php artisan migrate --seed
```

Seeding creates:
- 10 event categories (Birth, Move, Anniversary, Graduation, Milestone, Wedding, Travel, Career, Health, Other)
- A super-admin account (`admin@family.com` / `admin123`)
- "The Johnson Family" demo group (70 events, 1980–2024, 46 with images)

### Starting the dev environment

```bash
composer dev
```

This single command concurrently starts:
- **Laravel** (served by Herd at `http://timeline.test`)
- **Queue worker** (for background jobs)
- **Pail** (real-time log viewer)
- **Vite** (HMR at `http://localhost:5173`)

The app at `timeline.test` automatically picks up live React changes via the Vite dev server.

### Building for production / Herd

When the Vite dev server is **not** running, always build before refreshing:

```bash
npm run build
```

This compiles assets into `public/build/` and removes any stale `public/hot` file. After building, do a hard refresh in Edge (`Ctrl+Shift+R`).

> **Blank page?** If the page goes blank after stopping `npm run dev`, a stale `public/hot` file is the cause. Running `npm run build` fixes it automatically.

---

## Usage Guide

### Browse without an account

Visit `http://timeline.test/g/demo` to explore "The Johnson Family" timeline — a fully populated demo with 70 events and images, open to the public.

### Registration

1. Go to `http://timeline.test/register`.
2. Fill in your name, email, and password.
3. A referral code is optional — leave blank if you don't have one.

### Creating a group

1. Log in and go to **Dashboard**.
2. Click **Create a Group** and fill in the name and description.
3. You'll be taken to your new group's timeline.

### Inviting others to a group

1. Open **Group Settings** (⚙ button in the group header).
2. Copy the **Invite Code** shown in the Members section.
3. Share the code with anyone. They visit the group's public URL and enter the code to join.

### Adding events

1. Open your group's timeline.
2. Click **+ Add Event**.
3. Fill in: title, date, category, description, visibility settings, and optionally an image or album link.

### Visibility settings

Each event has two visibility controls:

**Group visibility** (who within the platform can see it):
- `public` — visible to anyone, including unauthenticated visitors
- `members` — visible to logged-in group members only
- `private` — visible only to the creator and group admins

**Social tier** (how broadly you share it, from most private to most public):
- Private → Family → Close Friends → Friends → Acquaintances → Public

You can also set **default visibility tiers per category** in your profile settings, so new events in that category default to your preferred tier.

### Using the Year Range slider

The right-hand sidebar on the group timeline is a vertical minimap of the full event span.

- **Scroll mode** (default): Drag the selection window to scroll the timeline to those years. The slider follows as you scroll the page manually.
- **Filter mode**: Click the **↕ Scroll** / **⊠ Filter** toggle to switch. In filter mode, events outside the selected range are hidden.
- **Resize**: Drag the coloured handles at the top and bottom of the selection window.
- **Jump**: Click anywhere outside the window to teleport it.
- **Direction**: The slider direction matches the sort order — newest at the top when sorting newest-first.

---

## Admin Panel

Super-admins access the Admin Panel from the top navigation.

**Capabilities:**
- View and manage all registered users
- Generate referral codes (set max uses and expiry)
- View referral code usage stats

Default super-admin login: `admin@family.com` / `admin123`

---

## Production Deployment

> **Hostinger / shared hosting note:** The server has no Node.js, so Vite cannot run
> there. `public/build/` is committed to git — always run `npm run build` locally and
> commit the updated `public/build/` before pushing to the server.

### 1. Build frontend assets

```bash
npm run build
git add public/build/
git commit -m "build: update compiled frontend assets"
```

### 2. Configure environment

Edit `.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_CONNECTION=sqlite      # or mysql for production
```

### 3. Optimise

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 4. Upload (Hostinger / shared hosting)

- Upload the entire project to a folder **above** `public_html` (e.g. `~/timeline_app/`).
- Symlink or copy the contents of `public/` into `public_html/`.
- Point your domain's document root to `public_html/`.

Since the frontend and API are in the same project, there are no CORS issues.

---

## Re-seeding the Demo

To reset the demo group to its original state (without affecting other data):

```bash
"C:\Users\r\.config\herd\bin\php.bat" artisan db:seed --class=DemoSeeder
```

The seeder is idempotent — it will not create duplicate events, but will re-apply images to existing events.
