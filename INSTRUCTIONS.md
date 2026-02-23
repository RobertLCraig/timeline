# Usage & Deployment Guide 📖

This document provides detailed instructions on how to use, configure, and deploy the Family Timeline application.

## 👥 User Roles & Permissions

### Platform Level
- **Super Admin**: Can manage referral codes, promote users to admins, and has full access to all groups/events.
- **User**: Standard registered user. Can create and join groups.

### Group Level
- **Owner**: The creator of the group. Full control over settings, members, and all events.
- **Admin**: Can manage members, invite codes, and all events within the group.
- **Member**: Can view events and post their own events.

## 🎟 Registration & Invites

The platform uses a two-tier invite system:

1.  **Platform Referral Codes**: Required to register a new account. These are created by Super Admins in the **Admin Panel**.
2.  **Group Invite Codes**: Used by registered users to join specific groups. These are managed by Group Owners/Admins in **Group Settings**.

## 📅 Managing Events

### Visibility Levels
- **🌍 Public**: Visible to anyone who visits the group link (even if not logged in).
- **👥 Members**: Only visible to registered members of that specific group.
- **🔒 Private**: Only visible to the event creator and group Admins/Owners.

### Media
- **Image Upload**: Upload a single featured photo for the event.
- **Album Link**: Provide a URL to an external photo album (e.g., Google Photos, Shared iCloud Album) for a full gallery experience.

---

## 🚀 Deployment Instructions

This app is optimized for **Hostinger Shared Hosting** (or similar cPanel/HPanel environments).

### 1. Backend Deployment (Laravel)

1.  **Prepare Files**: Run `composer install --optimize-autoloader --no-dev`.
2.  **Upload**: Upload the `backend` folder contents to your server (e.g., in a `api` subdirectory or separate folder).
3.  **Database**: 
    - Create a MySQL database and user in your Hosting panel.
    - Update the `.env` file on the server with its credentials.
4.  **Public Folder**: Set your web server's "Document Root" to point to the `public/` folder of the Laravel installation.
5.  **Migrations**: Run `php artisan migrate --seed` (via SSH or a cron job if SSH is unavailable).
6.  **Storage Link**: Run `php artisan storage:link`.

### 2. Frontend Deployment (React)

1.  **Build**: In the `frontend` folder, run `npm run build`.
2.  **CORS**: Update the `backend/.env` `FRONTEND_URL` to match your production domain.
3.  **Upload**: Upload the contents of the `frontend/dist` folder to your server's main public directory (e.g., `public_html`).
4.  **Routing**: Ensure you have an `.htaccess` file in `public_html` to handle SPA routing:

```apacheconf
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

---

## 🛠 Troubleshooting

- **CORS Errors**: Ensure the `FRONTEND_URL` in the backend `.env` exactly matches your frontend URL (including `https://`).
- **401 Unauthorized**: Sessions/tokens expire. Try logging out and back in.
- **Image Not Showing**: Ensure the `storage` symlink correctly points to `public/storage`.
