# Family Timeline — Technical Handover ⚡

This document provides a technical overview and handover for the **Family Timeline** project.

---

## 🏗 System Architecture

The application is built as a **decoupled SPA** (Single Page Application) with a **RESTful API** backend.

---

## Backend — Laravel 11

### Authentication
- **Laravel Sanctum** for token-based authentication.

### Core Models
- **User**  
  Handles platform-level roles (`user`, `super_admin`).

- **Group**  
  Represents a family/group unit. Generates unique slugs automatically.

- **Event**  
  Core content type.  
  - Belongs to a group and a creator  
  - Includes visibility tags  
  - Supports image and album URLs  

- **ReferralCode**  
  Controls platform registration.

- **GroupInvite**  
  Manages group membership invitations.

### Middleware
- **ResolveGroup** — Injects group model from slug.  
- **EnsureGroupRole** — Enforces `member`, `admin`, `owner` permissions.  
- **EnsureSuperAdmin** — Restricts platform‑level management.

---

## Frontend — React + Vite

### State Management
- **React Context (AuthContext)** for authentication state.

### API Layer
- Custom `api.js` wrapper around `fetch`:
  - Automatic token injection  
  - 401 interception and logout handling  

### Routing
- **react-router-dom** with `ProtectedRoute` wrappers for:
  - Authenticated access  
  - Admin‑level access  

### UI
- Vanilla CSS design system  
- Inter font  
- Dark‑mode aesthetic  
- CSS variables for theme consistency  

---

## 🔑 Critical Logic

### Event Visibility
Visibility is enforced in `App\Models\Event::isVisibleTo($user)`.

| Visibility | Who Can See It |
|-----------|----------------|
| `public` | Everyone |
| `members` | Logged‑in users who are members of the group |
| `private` | Creator OR group admin/owner |
| *Note* | Super Admins bypass all checks |

### Registration Flow
- Users must provide a **valid referral code** during registration.  
- Code is **consumed** (usage count incremented) when the account is created.

---

## 🗄 Database Schema Highlights

### `users`
- `platform_role` (`super_admin`, `user`)

### `groups`
- `slug` (unique)

### `group_members`
- Junction table  
- `role` (`owner`, `admin`, `member`)

### `events`
- `visibility` (`public`, `members`, `private`)  
- `image_url` (local)  
- `album_url` (external)

---

## 🛠 Future Improvements

- **Email Notifications**  
  Send group invites via email instead of sharing codes manually.

- **Live Comments**  
  Add real‑time commenting on events.

- **Media Galleries**  
  Support multiple image uploads per event.

- **Export/Import**  
  Allow families to download or migrate their entire timeline dataset.

---