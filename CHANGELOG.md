# Omincus Updates
## Omnicus v0.4.0 - Live Production Deployment

**Branch**: Production
**Date**: 2026-03-15

***

### Added

- Public production deployment announcement for TruX Social
- Official live URL reference: `https://truxsocial.org/`

***

### Changed

- Release status promoted from beta iteration to a fully deployed public website
- Changelog now tracks the first live production milestone as `v0.4.0`

***

### Notes

- The website is now fully deployed and accessible at `https://truxsocial.org/`.

## Omnicus v0.3.91 - Hotfix Rollup

**Branch**: Beta
**Date**: 2026-03-14

***

### Added

- New endpoint: `public/mark_conversation_read.php`
- Comment dock "Load older comments" control
- Cursor-based comment paging metadata in `public/post_comments.php` (`before`, `next_before`, `has_more`, `total_count`)

***

### Changed

- Notifications are no longer marked read on `GET /notifications.php`; they now use explicit POST action (`mark_all_read`)
- Conversation read state is no longer mutated during `GET /messages.php`; reads now flow through POST
- Comment dock now uses paged loading with stable scroll behavior when prepending older comments
- Upload file deletion logic is now centralized via `trux_delete_uploaded_file()`

***

### Fixed

- Apostrophes no longer get broken into accidental hashtag links (`#039`) when posts include hashtags/mentions
- Comment dock no longer shows truncated/incorrect comment totals on large threads
- Reply threads no longer lose hierarchy when parent comments are outside the initial page window
- Removed CSRF-able read-state side effects caused by GET-based notification/message read mutations
- Prevented null-user dereference edge cases in authenticated post create/delete handlers
- Prevented orphaned uploaded images when post creation fails after upload succeeds
- Reduced notification page/query overhead by removing per-row muted-user lookups (N+1 pattern)
- Migration reruns no longer fail on duplicate keys/constraints in `20260307_add_comment_replies.sql`

***

### Technical

- Added comment paging and ancestor expansion helpers in `src/posts.php`
- Extended comments API payload with `total_count`, `loaded_count`, and cursor metadata
- Added muted-user ID map helper in `src/mutes.php` and integrated map-based filtering in `src/notifications.php`
- Added POST read-state endpoint and messaging page integration for safe read updates
- Hardened post creation failure paths with upload cleanup and better defensive auth checks

***

### Notes

- Apply migrations before deploying this hotfix, especially if re-running migration scripts on existing environments.

## Omnicus v0.3.9 - Advanced Profiles & Premium Placeholder

**Branch**: Beta
**Date**: 2026-03-14

***

### Added

- Advanced profile editing page at `public/edit_profile.php`
- Real profile fields for display name, bio, location, website, avatar, and banner
- Profile media upload support for avatar and banner images
- Premium placeholder page at `public/premium.php`
- New profile menu entry for Premium with a diamond icon and `Coming soon` status
- New migration: `database/migrations/20260314_add_user_profile_fields.sql`

***

### Changed

- Profile pages now render saved profile banner and avatar when available
- Profile headers now show display name, bio, location, and website metadata
- Self profile action now links directly to Edit Profile
- Profile menu now includes an explicit Edit Profile shortcut
- Premium page now outlines four planned placeholder tiers: Basic Premium, Premium, Advanced Premium, and Premium+
- Premium tier cards now use progressive hover intensity so higher tiers feel more advanced on interaction

***

### Technical

- Added profile helper layer in `src/profiles.php`
- Added profile validation and website normalization for profile edit submissions
- Added animated GIF detection for avatar uploads to enforce Premium placeholder gating
- Updated user fetch queries to include profile fields in auth/profile/message contexts

***

### Notes

- Animated profile photos are marked as Premium-only and currently remain unavailable while Premium is still placeholder-only.

## Omnicus v0.3.8 - Bookmarks & Direct Messages

**Branch**: Beta
**Date**: 2026-03-09

***

### Added

- Functional private bookmarks for posts, comments, and replies
- New bookmarks page at `public/bookmarks.php`
- Initial 1-to-1 DM inbox and thread view at `public/messages.php`
- New message send endpoint: `public/send_message.php`
- Profile message entry point and header inbox entry with unread badge
- New migrations:
  - `database/migrations/20260309_add_bookmarks.sql`
  - `database/migrations/20260309_add_direct_messages.sql`

***

### Changed

- Post action bars now support live bookmark toggles
- Owner action menus for posts and comments now expose working bookmark actions instead of placeholders
- Bookmark menus and buttons now keep their `Bookmark` / `Saved` labels in sync
- Bookmark interactions now show non-blocking success toasts
- Bookmarks page now supports filters plus paginated loading for posts and comments

***

### Technical

- Added bookmark helper layer in `src/bookmarks.php`
- Added DM helper layer in `src/messages.php`
- Added `post_bookmarks` and `comment_bookmarks` tables for saved items
- Added `direct_conversations` and `direct_messages` tables for the first DM MVP
- Bootstrap now loads the DM helper stack and header unread counts now include DMs

***

### Notes

- The DM release is intentionally limited to simple text-only 1-to-1 conversations
- Realtime delivery, attachments, group chats, and message editing are not included in this version

## Omnicus v0.3.7 - Muted Users

**Branch**: Beta
**Date**: 2026-03-08

***

### Added

- User mute and unmute support
- New endpoint: `public/mute_user.php`
- Muted users section in Settings
- New migration: `database/migrations/20260308_add_muted_users.sql`

***

### Changed

- Notifications from muted users are no longer created
- Existing notifications from a user are cleared when that user is muted
- Profile pages now expose a mute toggle alongside follow controls

***

### Technical

- Added `muted_users` relationship table
- Added mute helpers in `src/mutes.php`
- Notification unread counts and notification feed queries now exclude muted actors

***

### Notes

- Muting only suppresses notifications; it does not block profiles, posts, follows, or search visibility.

## Omnicus v0.3.6 - Notifications & Preferences

**Branch**: Beta
**Date**: 2026-03-08

***

### Added

- Working notifications feed at `public/notifications.php`
- Notification delivery for post likes, comment votes, mentions, follows, new post comments, and replies
- Notification preferences section in Settings
- New migration: `database/migrations/20260308_add_notifications.sql`

***

### Changed

- Profile menu notifications entry now links to the live notifications page
- Settings now stores per-user notification preferences instead of showing only placeholders
- Post and comment mention detection now feeds the notifications system

***

### Technical

- Added `notifications` table for notification event storage
- Added per-user notification preference columns on `users`
- Added notification helper layer in `src/notifications.php`
- Notification event deduplication now uses a stable `event_key`

***

### Notes

- Comment vote notifications currently fire on upvotes only, which matches the app’s closest equivalent to comment likes.

## Omnicus v0.3.5 - Mention Autocomplete

**Branch**: Beta
**Date**: 2026-03-08

***

### Added

- Inline mention autocomplete while typing in post and comment textareas
- New JSON endpoint: `public/mention_suggestions.php`
- Clickable `@username` links in rendered posts and comment bodies

***

### Changed

- Comment payloads now include rendered rich-text HTML so mentions and hashtags display consistently in the live comment dock
- Compose forms now show a suggestion list above the textarea when a username mention prefix is detected

***

### Technical

- Added prefix-based username lookup helper in `src/search.php`
- Added shared mention normalization and rich-text rendering helpers in `src/helpers.php`
- Client-side mention autocomplete supports mouse selection plus arrow, enter, tab, and escape keyboard handling

***

### Notes

- Mention suggestions currently target the real textarea composers only; modal prompt-based edit flows remain plain text.

## Omnicus v0.3.4 - Hashtag Search & Tag Links

**Branch**: Beta
**Date**: 2026-03-08

***

### Added

- Hashtag extraction and storage for posts
- Clickable hashtag links inside post bodies
- Hashtag-only search filter on the search page
- New migration: `database/migrations/20260308_add_post_hashtags.sql`

***

### Changed

- Post create and post edit flows now sync hashtag records automatically
- Search now supports exact hashtag matching instead of plain text-only lookup
- Hashtag searches also fall back to raw post body matching so older posts remain discoverable

***

### Technical

- Added `post_hashtags` table for normalized per-post hashtag indexing
- Added hashtag helpers for extraction, normalization, and rendering
- AJAX post edit responses now return rendered post body HTML for hashtag links

***

### Notes

- This release adds a basic tagging layer without changing comment search or introducing hashtag administration.

## Omnicus v0.3.3 - Comment Vote System

**Branch**: Beta
**Date**: 2026-03-08

***

### Added

- Upvote and downvote controls for comments and replies
- SVG-based vote icons for comment voting UI
- Negative score display when downvotes exceed upvotes
- New vote endpoint: `public/vote_comment.php`
- New migration: `database/migrations/20260308_add_comment_votes.sql`

***

### Changed

- Comment API responses now include per-comment vote score and the current viewer's vote state
- Comment dock actions now support live AJAX vote toggles without page reload
- Comment and reply action rows now show vote controls alongside reply actions

***

### Technical

- Added `post_comment_votes` table to track per-user votes on comments and replies
- Added comment vote helpers in `src/posts.php` for vote toggling and vote aggregate retrieval
- Score calculation now uses net vote total so values can go below zero when downvotes outnumber upvotes

***

### Notes

- This release extends interaction parity from posts to comment threads while keeping the reply dock fully live and refresh-free.

## Omnicus v0.3.2 - Static UI Baseline & Settings Cleanup

**Branch**: Beta
**Date**: 2026-03-07

***

### Changed

- The application now runs on a fixed classic, reduced-motion interface across all pages
- Animated loading overlays, futuristic border effects, and optional visual modes are no longer part of the active UI
- The Settings page remains available as an account/settings placeholder, but visual controls have been removed
- README now documents the static UI baseline and visual preference cleanup path

***

### Technical

- Removed the runtime visual preference layer from bootstrap
- Removed `users.ui_reduce_motion` and `users.ui_classic_appearance` from the active schema definition
- Added cleanup migration: `database/migrations/20260307_drop_user_ui_preferences.sql`

***

### Notes

- This release formalizes the simpler visual baseline so development can prioritize missing product features over decorative interface options.

## Omnicus v0.3.1 - Owner Actions, Edit History & Menu Fixes

**Branch**: Beta
**Date**: 2026-03-07

***

### Added

- Owner-only action menus for posts, comments, and replies
- Edit and delete controls for authored posts, comments, and replies
- Bookmark action placeholder in owner menus
- Edit timestamp indicators for edited posts, comments, and replies
- New migration: `database/migrations/20260307_add_edited_timestamps.sql`

***

### Changed

- Owner actions now use shared SVG-based menu controls instead of delete-only buttons
- Edited timestamp markers are rendered only for items that have actually been edited
- README setup and migration notes now reflect the current interaction system

***

### Fixed

- Post owner action menu clipping against card borders
- Post owner action menu stacking over neighboring feed/profile cards

***

### Technical

- Added nullable `edited_at` columns to `posts` and `post_comments`
- Post edit and comment edit JSON endpoints now return edited timestamp metadata
- Comment API now returns edited timestamp metadata for comments and replies
- Client-side post edit flow now inserts or updates `EDITED AT` markers without page reload

***

### Notes

- This release tightens authored-content controls and edit history visibility while resolving dropdown layering issues on stacked post cards.

## Omnicus v0.3.0 - Post Interactions & Split Comment Dock

**Branch**: Beta
**Date**: 2026-03-07

***

### Added

- Functional post interactions
- Like toggle per post
- Share toggle per post
- Comment creation + retrieval flow
- New interaction tables
- `post_likes`
- `post_comments`
- `post_shares`
- New migration: `database/migrations/20260307_add_post_interactions.sql`
- Split comment dock UI
- Left pane shows clicked post for context
- Right pane shows live comments + comment form
- Works from feed and profile post cards
- Also wired on search results and single post page
- Comment reply support in comment dock
- Nested reply thread rendering inside each post comment list
- Reply composer state with target-aware hidden fields (`parent_id`, `reply_to_user_id`)
- Default `@mention` prefill when replying (including replying to a reply)
- Shared post action partial: `public/_post_actions_bar.php`
- Profile menu placeholder item
- Added `Notifications` entry under profile dropdown (`Coming soon`)
- New migration: `database/migrations/20260307_add_comment_replies.sql`

***

### Changed

- Replaced placeholder Like/Comment/Share buttons with live actions
- Added per-post interaction counters (likes/comments/shares)
- Added active states for liked/shared buttons
- Added comment modal scripts and styling in `public/assets/app.js` and `public/assets/style.css`
- Styled profile dropdown notification placeholder as disabled/non-interactive
- Comment list pane is now explicitly scrollable as threads grow
- Added profile icons next to usernames in comments and replies
- New post creation now supports AJAX submit without page reload
- Like/share actions now support AJAX toggles without page reload
- Time-ago labels now auto-refresh for posts, comments/replies, and joined timestamps

***

### Fixed

- Normal UI mode comment submit no longer leaves page transition/loading overlay stuck
- Transition FX now skips JS-handled/no-navigation comment form submissions

***

### Technical

- Added new post interaction helpers in `src/posts.php`
- Added endpoints
- `public/like_post.php`
- `public/share_post.php`
- `public/comment_post.php`
- `public/post_comments.php`
- Extended comment schema with `parent_comment_id` and `reply_to_user_id`
- Added JSON response mode (`?format=json`) for `new_post.php`, `like_post.php`, and `share_post.php`
- Added reusable global comment dock markup in `public/_footer.php`
- Added interaction map wiring in:
- `public/index.php`
- `public/profile.php`
- `public/search.php`
- `public/post.php`
- Updated docs to reflect interaction feature availability

***

### Notes

- This release turns core social actions from placeholder to functional interaction loops while keeping in-context commenting via split dock UX.

## Omnicus v0.2.0 — UI Overhaul & Follow System

**Branch**: Beta
**Date**: 2026-02-28

***


### Added

- Follow / Unfollow system

- follows table (composite PK, indexed, FK constraints)

- Real-time follower & following counts

- Conditional follow button states

- CSRF protection for follow actions

- Neon sweep animation for buttons

- Orbit animation for circular action buttons

- Profile identity panel redesign

- Followers / Following / Joined metadata section

***

### Changed

- Complete navbar redesign

- Username link replaced with profile icon

- Introduced circular "+" post creation button

- Moved logout into profile dropdown

- Reduced footer height

- Improved dropdown hitbox & hover stability

- Refined neon border intensities

- Post card futuristic border animations

- Enhanced hover transitions and micro-interactions

- Improved gradient background rendering

***

### Fixed

- Profile link redirecting to wrong user

- Login button visible while authenticated

- Dropdown disappearing on hover

- Button overlap hiding profile icon

- Fatal error when follows table missing

- Multiple UI alignment inconsistencies

***

### Technical

- Follow queries implemented with PDO prepared statements

- POST-only follow endpoint

- Self-follow prevention

- Foreign key cascade deletion

- Indexed follow relationships for scalability

***

### Notes

- This version establishes:

- Stable UI direction

- Functional social graph foundation

- Expandable interaction architecture

## Omnicus v0.2.1 - Performance & Accessibility Hotfix

**Branch**: Beta
**Date**: 2026-03-01

***

### Added

- New Settings page under Profile menu

- Reduce motions toggle

- Classic appearance toggle

- Persistent UI preference layer (`src/ui.php`)

- Account-level UI settings storage (`users.ui_reduce_motion`, `users.ui_classic_appearance`)

- DB migration for existing installs (`database/migrations/20260301_add_user_ui_preferences.sql`)

***

### Changed

- Default visual preset changed from `cyber--extreme` to `cyber--balanced`

- Profile dropdown now includes direct Settings entry

- Reduced-motion and classic modes now disable heavy transition/animation paths

- Feed/profile/search post images now use lazy loading and async decoding

- README updated with migration instructions

***

### Fixed

- Browser slowdown from duplicate `app.js` include (removed footer duplicate)

- Mis-encoded text artifacts (mojibake) in auth/register/footer/README copy

- Navigation transition overhead now skipped when reduced motion/classic mode is active

- Several whitespace/newline diff-noise issues in touched files

***

### Technical

- UI preferences read/write logic now supports DB + session/cookie fallback

- Safe fallback implemented when UI preference columns are not yet migrated

- Header body class composition now driven by saved preferences

- CSS includes explicit `motion--reduced` and `appearance--classic` optimization rules

***

### Notes

- This hotfix focuses on lowering GPU/CPU/RAM pressure without removing the core visual identity

- Users can now choose visual fidelity level per account for smoother browsing on lower-end devices

## Omnicus v0.1 - Initial Release
Initial Releases

