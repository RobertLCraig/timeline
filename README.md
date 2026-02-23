# Family Timeline ⏳

A premium, secure, multi-user web application designed for families to record and share life events. This project is a unified **Laravel + React** monolith.

![Dashboard Preview](https://via.placeholder.com/800x400.png?text=Family+Timeline+Dashboard)

## ✨ Features

- **Multi-Family Groups**: Create private spaces for different circles.
- **Interactive Timeline**: A chronological story with category-based filtering.
- **Granular Privacy**: Public, Members-only, or strictly Private events.
- **Referral System**: Registration restricted via admin-managed codes.
- **Unified Structure**: Laravel-powered API and React-powered UI in one place.

## 🚀 Quick Start (Laravel Herd)

The project is already configured for [Laravel Herd](https://herd.laravel.com/).

1. **Link Project**: Open Herd, go to **Sites**, and ensure the `timeline` folder is listed (Path: `C:\Dev\timeline`).
2. **Access Root**: Visit `http://timeline.test` in your browser.
3. **Setup Database**:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```
4. **Development (Vite)**:
   ```bash
   npm install
   npm run dev
   ```

## 🛠 Tech Stack

- **Backend**: Laravel 11
- **Frontend**: React 18 + Vite (Integrated via Laravel Vite Plugin)
- **Database**: MySQL / SQLite
- **Styling**: Vanilla CSS with dark-mode aesthetic

## 📖 Documentation

For detailed setup, usage guide, and deployment instructions, see [INSTRUCTIONS.md](./INSTRUCTIONS.md).
