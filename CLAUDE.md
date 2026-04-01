# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TruX is a social media platform (Twitter/X-like) built with pure PHP + MySQL — no framework. Features include posts, comments, likes, shares, bookmarks, follows, DMs, notifications, OAuth, and a staff moderation suite.

## Running the Project

**XAMPP (Windows — primary dev environment):**
1. Start Apache + MySQL in XAMPP Control Panel
2. DocumentRoot must point to `/public`
3. Import `database/schema.sql` to MySQL, then apply migrations in order from `database/migrations/`
4. Copy `.env.example` → `.env` and fill in credentials

**PHP built-in server:**
```bash
php -S localhost:8000 -t public
```

## Database Migrations

Migrations are in `database/migrations/` named `YYYYMMDD_description.sql`. Apply them in chronological order after the base `schema.sql`. There is no rollback mechanism — migrations are append-only.

## Architecture

### Routing
File-based routing — URLs map directly to PHP files in `public/`. No router or dispatcher.

### Request Lifecycle
Every page begins with `require '_bootstrap.php'`, which loads all `src/` modules, initializes the PDO connection, starts the session, and checks for access blocks.

### Source Modules (`src/`)
Each file is a self-contained feature module exporting functions:
- `config.php` — env var loading
- `db.php` — PDO singleton (`get_db()`)
- `auth.php` — session, login/logout, registration
- `csrf.php` — token generation and verification
- `posts.php` — post CRUD, likes, comments, shares
- `profiles.php` — user profiles
- `messages.php` — DM conversations
- `notifications.php` — notification feed
- `moderation.php` — reports, appeals, audit logs, access blocks (large module)
- `discovery.php` — feed ranking algorithm, trending hashtags, user suggestions
- `follows.php`, `blocks.php`, `mutes.php` — social graph
- `upload.php` — image upload validation and re-encoding
- `mailer.php` / `email_helpers.php` — PHPMailer-based email
- `helpers.php` — utility functions (timestamps, escaping, redirects, flash messages)
- `linked_accounts.php` — OAuth provider connections

### AJAX Handlers
Interaction endpoints (likes, comments, follow, etc.) are standalone PHP files in `public/` that return JSON. They follow a consistent pattern: validate CSRF → check auth → perform DB action → return JSON.

### PHP Template Partials
Reusable UI components are prefixed with `_` in `public/`:
- `_header.php`, `_footer.php` — page chrome
- `_post_card.php`, `_post_actions_bar.php` — post display
- `_dm_conversation_item.php`, `_dm_message_bubble.php` — messaging UI

### Frontend
- `public/assets/app.js` — main application logic, AJAX, UI interactions (vanilla JS)
- `public/assets/messages_v2.js` — messaging UI with long-poll real-time updates
- No build tool — JS files loaded directly

CSS is organized in `public/assets/css/` with subdirectories: `base/`, `components/`, `layout/`, `pages/`, `theme/`, `responsive/`.

## Key Conventions

- **Database:** PDO with prepared statements everywhere, emulation disabled. Use `get_db()` from `src/db.php`.
- **Auth checks:** Call `require_login()` (from `auth.php`) at the top of protected pages.
- **CSRF:** All state-changing POSTs must call `verify_csrf_token()` and forms must include `generate_csrf_token()`.
- **Output escaping:** Use `htmlspecialchars()` (often aliased as `e()` in helpers) on all user-generated content.
- **Redirects with messages:** Use `redirect_with_message($url, $message, $type)` from `helpers.php`.
- **IDs:** All tables use `BIGINT UNSIGNED` primary keys.
- **Charset:** UTF-8MB4 throughout (supports full Unicode including emoji).

## Environment Variables

Loaded from `.env` via `src/config.php`. Key groups:
- `TRUX_DB_*` — database connection
- `TRUX_APP_NAME`, `TRUX_BASE_URL`, `TRUX_TIMEZONE`
- `TRUX_MAIL_*` — SMTP (PHPMailer)
- `TRUX_DISCORD_*`, `TRUX_GOOGLE_*`, `TRUX_FACEBOOK_*`, `TRUX_X_*` — OAuth providers

## File Uploads

- Post/profile images: `public/uploads/` (PHP execution blocked via `.htaccess`)
- DM attachments: `storage/dm_attachments/`
- Max 4 MB, max 4096×4096 px, allowed types: JPEG, PNG, GIF, WebP
- Images are re-encoded to strip metadata

## Feature Roadmap

### Implemented (complete or near-complete)
- **Quote posts** — Repost-with-commentary; stored as first-class posts with `quoted_post_id` FK; embedded original renders inside quoting post card; notification sent to original author
- **Post polls** — 2–4 options, optional expiry, one vote per user, progress bars after voting; `polls` / `poll_options` / `poll_votes` tables
- **Dark mode toggle** — `theme_preference` column on `users`; FOUC-free toggle persists in DB + localStorage; light-mode token overrides in `redesign-dark.css`
- **Pinned posts** — pin one post per user to profile top; auto-unpin on replace; pin badge on card; `is_pinned` column on `posts`
- **Posts** — create, edit, delete, image attachments, hashtags, mentions
- **Comments/Replies** — nested threading, upvote/downvote, edit, delete
- **Likes** — post likes + comment votes
- **Shares** — basic repost (no quote posts yet)
- **Bookmarks** — posts & comments, privacy controls
- **Follow system** — follow/unfollow, follower/following counts
- **User profiles** — display name, bio, avatar, header, website, custom links, profile tabs
- **Direct Messages (v2)** — 1-to-1 conversations, image/PDF attachments, emoji reactions, reply threading, read receipts, message edit/unsend within grace window, long-poll real-time updates
- **Notifications** — 6+ types (likes, follows, mentions, replies, reports), per-type preferences, deduplication
- **Search** — users, posts, hashtags (two-mode search UI)
- **Discovery feed** — ranking-based "For You" feed, trending hashtags, suggested users
- **Blocking & muting** — bidirectional block, mute suppression
- **Moderation suite** — reports, appeals, escalations, user cases, audit logs, suspicious-activity rules, staff roles
- **Account settings** — email change, password change, notification prefs, privacy prefs, linked OAuth accounts
- **Email verification** — time-limited token flow via PHPMailer/SMTP
- **OAuth framework** — Discord, Google, Facebook, X, Nicholic (scaffolded; provider integrations may need credentials)

### Partially implemented / in-progress
- **Typing indicators** — HTML/CSS placeholder exists in DMs; backend not wired up
- **Premium tier** — placeholder page (`public/premium.php`) exists; no tier logic, billing, or feature gating
- **Verified badges** — field exists in DB; UI shows "coming soon"
- **OAuth provider integrations** — framework complete; actual provider credential flows need end-to-end testing

### Missing / not yet started
| Feature | Priority | Notes |
|---|---|---|
| Two-factor authentication (2FA) | Medium | TOTP or email-based OTP |
| Session management | Medium | List active sessions, revoke individual devices |
| Account deletion | Medium | Self-service account removal |
| Account data export | Medium | GDPR-compliant data download |
| Drafts | Medium | Save posts before publishing |
| Post scheduling | Medium | Publish posts at a future time |
| Group/multi-person DMs | Medium | Extend messaging beyond 1-to-1 |
| Typing indicators (DMs) | Medium | Real-time "is typing" broadcast |
| Post visibility settings | Medium | Per-post audience controls (public / followers only) |
| Push notifications | Medium | Service worker + browser push API |
| Accessibility (WCAG 2.1 AA) | Medium | Audit and remediate keyboard nav, contrast, screen-reader support |
| Advanced search filters | Low | Date range, author, language filters |
| Analytics dashboard | Low | Per-user engagement metrics |
| Post media gallery | Low | Grid view of all media on a profile |
| DM media gallery | Low | Gallery view of all attachments in a conversation |
| Spaces / live audio | Low | Live audio rooms (WebRTC or third-party) |
| Communities / groups | Low | User-managed topic communities |
| Lists | Low | Curated follow lists |
| Custom emoji | Low | Server-hosted emoji sets |
| API / developer access | Low | Public REST API with API keys |
| Caching layer | Low | Redis/Memcached for hot queries |
| WebSocket real-time | Low | Replace long-poll with WebSocket |
