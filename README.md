# Family Timeline ⏳

A premium, secure, multi-user web application designed for families to record and share life events in a beautiful, interactive timeline.

![Landing Page Preview](https://via.placeholder.com/800x400.png?text=Family+Timeline+Preview)

## ✨ Features

- **Multi-Family Groups**: Create private spaces for different branches of your family or friend circles.
- **Beautiful Interactive Timeline**: A chronological story of your family, from births to weddings and everything in between.
- **Granular Privacy**: Control visibility for every event (Public, Group Members only, or strictly Private).
- **Media Support**: Upload photos directly or link to external albums (Google Photos, iCloud, etc.).
- **Role-Based Access**: Manage group members with Owner, Admin, and Member roles.
- **Secure Registration**: Platform access is restricted via admin-managed referral codes to ensure a safe environment.
- **Modern Dark UI**: A high-end, responsive design with glassmorphism and smooth animations.

## 🛠 Tech Stack

- **Backend**: [Laravel 11](https://laravel.com/) (PHP 8.2+)
- **Frontend**: [React](https://reactjs.org/) + [Vite](https://vitejs.dev/)
- **Database**: MySQL / SQLite
- **Authentication**: Laravel Sanctum (Token-based)
- **Styling**: Vanilla CSS (Custom Design System)

## 📁 Project Structure

- `/backend`: Laravel API project
- `/frontend`: Vite + React SPA project

## 🚀 Quick Start (Local Development)

### Prerequisites

- PHP 8.2+ & Composer
- Node.js & npm
- SQLite (default) or MySQL

### 1. Setup Backend (Herd)

If you use **Laravel Herd**:
1. Open Herd and go to **Sites**.
2. Drag the `backend` folder into Herd.
3. Note the URL (usually `http://backend.test`).
4. In `frontend/.env.local`, ensure `VITE_API_URL` matches your Herd URL + `/api`.

Alternatively, via CLI:
```bash
cd backend
php artisan migrate --seed
php artisan storage:link
```

### 2. Setup Frontend

```bash
cd frontend
npm install
npm run dev
```

Visit `http://localhost:5173` to see the app!

## 📖 Documentation

For detailed setup, usage guide, and deployment instructions, see [INSTRUCTIONS.md](./INSTRUCTIONS.md).

## 📄 License

Proprietary. All rights reserved.
