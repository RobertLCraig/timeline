# Usage & Deployment Guide (Unified Monolith) 📖

This guide covers the Family Timeline application, now unified into a single Laravel + React project.

## 🚀 Development Setup

### Laravel Herd (Recommended)
1. Ensure the project is linked in **Herd** (Path: `C:\Dev\timeline`).
2. Your site is available at `http://timeline.test`.

### Database
```bash
php artisan migrate --seed
php artisan storage:link
```

### Frontend (HMR)
To enjoy Hot Module Replacement for the React UI:
```bash
npm install
npm run dev
```
Visit `http://timeline.test` — Laravel will automatically proxy to the Vite dev server.

---

## 👥 Usage Guide

### Registration
The platform requires a **Referral Code**.
1. Log in as a Super Admin (see `DatabaseSeeder.php` for initial credentials).
2. Go to the **Admin Panel** to generate referral codes.
3. Share the code with new users to allow them to register.

### Groups & Timelines
1. Create a group (family, group of friends, etc.).
2. Go to **Group Settings** to generate a **Group Invite Code**.
3. Other registered users can use this code to join your specific group timeline.

---

## 🌎 Production Deployment (Hostinger)

Deployment is simplified in the unified structure:

1. **Build Frontend**: Run `npm run build`. This generates files in `public/build`.
2. **Environment**: Update `.env` with production database and `APP_URL`.
3. **Upload**: 
   - Upload the entire project to a folder (e.g., `timeline_app`) **above** your `public_html`.
   - Move or symlink the contents of the project's `public/` folder into `public_html`.
4. **CORS**: Since it's a monolith, CORS issues are naturally non-existent.
5. **Optimization**:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
