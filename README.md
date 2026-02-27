# TruX (simple social media web app)

TruX is a lightweight PHP + MySQL/MariaDB social feed with:
- User registration + login/logout (sessions)
- Create posts (text + optional image upload)
- View global feed, single post pages, and user profiles
- Owner-only post deletion (CSRF-protected)
- Basic search (users + post text)
- Pagination on feed + profiles + search results
- Pretty timestamps (“5 minutes ago”) with exact time on hover

## Requirements
- PHP 8.1+ (PDO MySQL + fileinfo extensions enabled)
- GD extension enabled (for image re-encode hardening)
- MySQL 8+ or MariaDB 10.4+
- A web server (Apache/Nginx) or PHP built-in server

## Security highlights
- Prepared statements everywhere (PDO, emulation disabled)
- password_hash / password_verify
- Session hardening + session ID regeneration on login
- CSRF protection on all POST forms
- Upload validation: MIME (finfo) + dimensions + size limit + random filenames
- Upload hardening: image is re-decoded + re-encoded (strips metadata)
- Uploads directory blocks PHP execution via .htaccess

## How to run locally

### Option A: PHP built-in server (quick)
1) Create DB + tables (see schema.sql).
2) Copy `.env.example` -> `.env` and edit DB settings.
3) Run:
   php -S localhost:8000 -t public
4) Open: http://localhost:8000

### Option B: XAMPP (Windows)
1) Put project under: C:\xampp\htdocs\truxv2 (or similar)
2) Start Apache + MySQL in XAMPP Control Panel
3) Import database/schema.sql via phpMyAdmin or mysql.exe
4) If Apache runs on 8080, set:
   TRUX_BASE_URL=http://truxv2.local:8080
5) Configure a vhost pointing DocumentRoot to the /public folder.

## Implemented vs not present
Implemented:
- Auth (register/login/logout), sessions, CSRF
- Create posts + uploads (validated + re-encoded)
- View feed, single post, profile
- Pagination (feed/profile/search)
- Owner-only delete posts
- Search (users + post text)

Not present:
- Likes/comments/follows/DMs/notifications
- Admin/moderation tools, reports
- Password reset email flow

## Changelog
Changelog: [Changelog](CHANGELOG.md)