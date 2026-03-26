# Omnicus Updates
## Omnicus v0.5.5 - Full UI Replacement

**Branch**: Production
**Date**: 2026-03-24

***

### Added

- New command-shell app foundation with a persistent desktop rail, sticky context topbar, fluid content canvas, and mobile bottom navigation
- New full-screen account gateway for `login`, `register`, `forgot_password`, and `reset_password`
- Mobile search sheet and account sheet for the new shell navigation model
- New modular stylesheet architecture under `public/assets/css/` using base, layout, components, pages, effects, and responsive layers
- New `v0.5.5` UI release notes documenting the full shell cutover

***

### Changed

- Replaced the old centered-card UI paradigm with layered command-shell surfaces across feed, search, bookmarks, notifications, messages, profile, settings, composer, premium, and appeal pages
- Rebuilt the shared header and footer templates into shell routers for `app`, `auth`, and `moderation` layouts
- Rebuilt the shared post renderer so posts read as stream bands with a gutter, content lane, and inline action rail instead of generic cards
- Reworked the moderation shell into a distinct ops workspace with a dedicated staff rail and queue strip
- Reorganized mobile behavior so navigation, focused threads, sheets, and overlays behave like a deliberate phone UI rather than compressed desktop chrome
- Rebalanced shell density toward a leaner center-feed layout with slimmer rails, tighter headers, and less oversized page chrome
- Moved feed mode controls into the live timeline header and tightened profile, message, and utility surface spacing
- Standardized the shared post viewer and moderation review overlays into fixed-height popup workspaces with internal scrolling instead of mixed inline/route presentation

***

### Technical

- Replaced the CSS entrypoint with `public/assets/css/main.css` and aligned imports to the new modular file structure
- Added shell-only client behavior in `public/assets/app.js` for search and account sheets while preserving existing post, modal, AJAX, and moderation flows
- Preserved backend logic, routes, database behavior, auth/session/CSRF handling, moderation logic, messaging logic, validation rules, and API response shapes
- Folded active support and utility selectors into the imported modular CSS so premium tiers, notification danger actions, cards, and shared flashes no longer depend on dead legacy files

***

### Fixed

- Restored working cropper apply flow by falling back to client-side image cropping when server-side GD cropping is unavailable
- Fixed rail account presence to use the uploaded profile photo, truncate long usernames cleanly, and align moderation badges correctly
- Fixed dropdown hover behavior so the profile menu stays open while moving the cursor from trigger to panel, and added SVG icons to profile menu items
- Tightened post, comment, and profile action menus so they size to their content and no longer overlap nearby controls
- Restored missing comment-viewer avatar, vote, score, and action styles after the UI cutover
- Fixed profile facts/sidebar stretching, profile-action clipping, false `No posts yet` append behavior on paged profiles, and oversized custom banner rendering
- Removed the visible standalone post-viewer page composition while keeping the shared popup viewer route working as the direct entry point
- Restored moderation report/user-review popup presentation so review workspaces no longer render like broken inline pages

***

## Omnicus v0.5.0

**Branch**: Production
**Date**: 2026-03-23

***

### Added

- Staff-only moderation area with dashboard, reports queue, suspicious activity review, user review workspace, audit logs, and admin-only `Staff Access`
- Real moderation modules for `Escalations`, `Rule Tuning`, and `Appeals`, plus the public `/appeal.php` route for account-action appeals
- Full user-case lifecycle support with case status, priority, resolution fields, closure metadata, escalation linkage, linked notes, linked reports, linked suspicious events, linked enforcements, and linked appeals
- Account-level enforcement history for confirmed violations, including warnings, DM restrictions, timed suspensions, and account locks
- Structured report finalization with content outcome, optional case creation/update, optional account action, optional suspension end time, and resolution notes
- Suspicious-activity triage actions for assignment, review, false-positive, reopen, open linked report, open/create linked user case, and escalate to the admin queue
- Target-owner moderation notifications for removed posts, comments, messages, and account-level enforcement outcomes, with moderation deep links
- Persistent reporter-update default in Settings and prechecked report-modal behavior for users who opt into report-update DMs by default
- Moderation queue badges in the header profile menu and moderation sidebar for reports, user review, suspicious activity, escalations, and appeals
- Database-backed moderation rule tuning for `repeated_failed_login`, `content_burst`, `dm_burst_multiple_recipients`, `multiple_reports_same_account`, `spam_link_burst`, `duplicate_content_burst`, `follow_burst`, and `multiple_blocks_same_account`
- New moderation/reporting migrations:
  `database/migrations/20260320_add_blocked_users.sql`,
  `database/migrations/20260321_add_moderation_foundations.sql`,
  `database/migrations/20260321_add_reporter_dm_updates_preference.sql`,
  `database/migrations/20260321_add_report_system_user.sql`,
  `database/migrations/20260321_add_moderation_report_review_workspace.sql`,
  `database/migrations/20260323_add_unified_reporting_and_user_cases.sql`,
  and `database/migrations/20260323_complete_moderation_v050.sql`

***

### Changed

- Moderation dashboards, queues, and review tools now use a shared responsive moderation shell for desktop and mobile, with live queue badges instead of placeholder future-module cards
- Report reviews now happen through the dedicated review popup instead of inline queue actions, and archived reports still require owner-only reopen before re-review
- Reporter DMs now cover submitted, resolved, and dismissed report outcomes without pretending the per-report checkbox changed the global account preference
- Suspicious-rule evaluation now reads tunable thresholds from the database instead of hardcoded values
- Post, comment, reply, DM, follow, and block activity logging now carries the metadata needed for rule tuning and burst detection, including text fingerprints and link counts where relevant
- The moderation workspace now treats account-action appeals as public token-based flows so suspended or locked users can still submit an appeal

***

### Fixed

- Reports page filters now correctly show all matching reports when `All` is selected
- Suspended and locked accounts are now forced out of active sessions instead of only being blocked on the next login attempt
- DM-restricted accounts can no longer send outbound direct messages while still receiving moderation/system updates
- Reporter-update defaults now persist from Settings into the report modal instead of making reporters recheck the box every time
- Moderation notifications can now deep-link into the correct report, case, escalation, appeal, or appeal form destination without overloading post/comment notification fields
- The internal `Report System Updates` identity is no longer exposed like a normal public profile/account

***

### Technical

- Expanded the moderation helper layer with enforcement, escalation, appeal, suspicious-triage, rule-config, queue-badge, and case-workflow services
- Added report discussion and vote persistence plus archived-report immutability rules for non-owner staff
- Added moderation/security activity recording for login, posting, comments, DMs, follow, mute, and block flows, including the new heuristic metadata fields used by v0.5.0 rules
- Extended notifications with nullable `target_url` and added moderation outcome notification types for comment removal, message removal, warnings, DM restrictions, suspensions, and locks
- Added new staff/reporting routes: `/moderation/`, `/moderation/reports.php`, `/moderation/activity.php`, `/moderation/user_review.php`, `/moderation/escalations.php`, `/moderation/rule_tuning.php`, `/moderation/appeals.php`, `/moderation/audit_logs.php`, `/moderation/staff.php`, `/report.php`, `/report_post.php`, `/appeal.php`, and a compatibility redirect for `/assign_staff_role.php`

***

## Omnicus v0.4.10 - UX Improvements

**Branch**: Production
**Date**: 2026-03-20

***

### Fixed

- Fixed the owner post action menu bookmark flow so clicking `Bookmark` on your own post no longer throws `setActionActive is not defined`
- Owner and non-owner post bookmark toggles now keep the three-dot menu state and the main post action bar label/state in sync
- New post publishing now redirects straight to the new post instead of leaving a static success message behind
- Fixed bookmarked post/comment filter and pager links on `public/bookmarks.php` so they stay under `TRUX_BASE_URL` instead of generating broken root-level URLs
- Fixed header brand hover scoping so hovering the new home icon no longer triggers the logo/text hover animation
- Fixed browser-tab favicon sizing by cropping the visible logo area before rendering the shared favicon asset

***

### Added

- Posts now support click-to-open comments from the card itself, with pointer-style hover feedback across the feed cards
- New shared post menu for all posts with working bookmark and copy-link actions, plus placeholder `Not interested`, `Mute user`, and `Report` actions for non-owner posts
- Dedicated mobile override stylesheet at `public/assets/mobile.css` that loads automatically for smaller screens and tightens shared layouts across feed, profile, settings, messages, and the comment dock
- Bookmark actions on posts now show a visible saved-count badge alongside likes, comments, and shares
- Renamed the shared post popup UI from `Post Comments` to `Post Viewer`
- Post Viewer now keeps post actions in the popup preview column with `Like`, `Share`, `Bookmark`, and the three-dot post menu while leaving `Comment` to the thread pane
- Notification hover menu now includes a real `Clean all` action alongside `Mark all as read`
- Rich-text bodies now auto-link pasted `http://` and `https://` URLs across posts, comments, and replies
- Header branding now shows the project logo to the left of the plain `TruX` label plus a dedicated home icon button on the right

***

### Changed

- Post media now uses a more restrained X-style presentation with capped image height so tall uploads do not dominate the viewport
- Post body, media, and action rows now share a narrower readable width so large desktop cards feel more balanced
- Post pages across `public/index.php`, `public/search.php`, `public/profile.php`, `public/bookmarks.php`, and `public/post.php` now use the same shared context-menu rendering
- Direct post links, new-post redirects, notification targets, and comment/reply-related post redirects now open through the `Post Viewer` flow instead of relying on the old standalone single-post experience
- The standalone `public/post.php` page is now treated as a hidden Post Viewer entry route, so users land in the popup UI instead of a visible duplicate post page
- Post-related notifications that point to comments or replies now open the Post Viewer with the target comment highlighted
- Internal site links such as `http://localhost/truxv2/public/post.php?id=8` are now normalized to the `Post Viewer` route when rendered in posts, comments, and replies
- Inserted links in posts, comments, and replies now use the shared blue link treatment already used for mentions and hashtags
- The post action menu now hides the redundant `Open viewer page` entry when that menu is rendered inside Post Viewer itself
- The header brand is no longer a linked underlined text label; it now behaves as a branded display block with hover animation while the separate home icon owns navigation back to the main page

***

### Technical

- Added same-scope post bookmark state syncing plus shared menu action handlers in `public/assets/app.js`
- Added reusable post context menu markup in `public/_post_content_menu.php`
- Added automatic mobile stylesheet loading in `public/_header.php`
- Added shared Post Viewer URL helpers in `src/helpers.php` and reused them across post, notification, comment, bookmark, and edit redirect flows
- Extended `src/helpers.php` rich-text rendering so URL autolinking and internal post-link normalization happen in one shared path for posts, comments, replies, and profile rich text
- Added notification cleanup support in `src/notifications.php` plus matching controller handling in `public/notifications.php`
- Updated `public/favicon.php` to generate a tighter rendered favicon from `src/logo/trux_logo.png` instead of streaming the raw padded source image
- Reworked header brand markup in `public/_header.php` with matching animation/responsive rules in `public/assets/style.css` and `public/assets/mobile.css`

***

## Omnicus v0.4.9 - Profile Media Cropper

**Branch**: Production
**Date**: 2026-03-20

***

### Added

- Fixed-aspect crop workflow for profile photo and profile banner uploads on `public/edit_profile.php`
- Reusable profile media crop modal with drag-to-position, zoom control, and live preview
- Automatic crop flow that opens immediately after a user selects a new profile image from their library
- Browser tab logo/favicon sourced from `src/logo/trux_logo.png`

***

### Changed

- Profile media cards now show local cropped previews before save and let users reopen the crop tool for the current pending selection
- Profile media uploads now carry crop metadata so the server applies the same crop chosen in the browser
- Profile media cropping now depends on PHP GD being enabled in the deployment environment so the server can process the selected crop safely
- `Edit crop` now works for already-saved profile photos and banners by reopening the cropper on the current image, and falls back to the file picker when no image exists yet
- The profile hover menu is now slimmer by hiding the duplicate `Edit Profile` shortcut and temporarily hiding the placeholder `Premium` entry until it is ready to ship

***

### Technical

- Added crop payload parsing and GD-based crop support to `src/upload.php`
- Added profile media crop UI hooks in `public/edit_profile.php`
- Added cropper behavior in `public/assets/app.js` and matching modal/preview styles in `public/assets/style.css`
- Added simple header menu visibility toggles in `public/_header.php` so placeholder entries can stay in code without appearing in the live menu
- Added `public/favicon.php` plus the shared `<link rel="icon">` hook in `public/_header.php` so the browser tab can load the project logo from `src/logo`
- Verified syntax with `C:\\xampp\\php\\php.exe -l public\\edit_profile.php`, `C:\\xampp\\php\\php.exe -l src\\upload.php`, and `node --check public\\assets\\app.js`
- Confirmed local XAMPP PHP GD support during validation so crop uploads can complete end to end
- Added client-side recrop support that can load an existing saved avatar/banner back into the upload input before applying a new crop

***

## Omnicus v0.4.8 - Header Notifications & Infinite Feed Loading

**Branch**: Production
**Date**: 2026-03-19

***

### Added

- New header notification dropdown with unread badge, recent activity preview, and quick access to the full notifications page
- Quick `Mark all as read` action inside the header notification menu
- Automatic loading of older posts while scrolling on the home feed, search results, profile post sections, and bookmarked posts
- Visible pager loading/error states for auto-loaded post lists

***

### Changed

- Header search now uses an icon-first expanding button treatment instead of a plain text submit control
- Mark-all-read notification flows now support safe in-app redirect targets so users can return to the page they were on
- Profile masthead styling now groups hero, stats, and tabs into a more unified card layout
- Existing `Load more` links remain as non-JavaScript fallback while feeds progressively append older posts when JavaScript is available

***

### Technical

- Added recent-notification preload and redirect-path handling in `public/_header.php`
- Added redirect validation support to `public/notifications.php`
- Added client-side auto-pager logic in `public/assets/app.js` and matching pager state styles in `public/assets/style.css`
- Added auto-pager markup hooks in `public/index.php`, `public/search.php`, `public/profile.php`, and `public/bookmarks.php`
- Verified syntax with `C:\\xampp\\php\\php.exe -l` on `public/_header.php`, `public/notifications.php`, `public/index.php`, `public/search.php`, `public/profile.php`, and `public/bookmarks.php`, plus `node --check public/assets/app.js`

***

## Omnicus v0.4.7 - Profile Tabs & Settings Navigation

**Branch**: Production
**Date**: 2026-03-19

***

### Added

- New profile section tabs on `public/profile.php`: `Posts`, `Replies`, `Liked`, `Bookmarks`, and `About Me`
- Long-form `About Me` profile field with support for up to 5 affiliated links
- Automatic social/platform icon rendering for supported profile links such as Reddit, Instagram, GitHub, YouTube, Discord, and more
- Live icon/label preview inside the affiliated link editor on `public/edit_profile.php`
- New profile privacy controls in `public/settings.php` for public likes and bookmarks visibility
- New migration: `database/migrations/20260319_add_profile_tabs_and_privacy.sql`

***

### Changed

- Profile pages now render user-authored replies/comments in a dedicated `Replies` section
- Profile pages now render liked posts and liked comments/replies in a dedicated `Liked` section
- Profile pages now render bookmarked posts and bookmarked comments/replies in a dedicated `Bookmarks` section
- Settings now use left-side section navigation with one active section shown at a time instead of a single long scrolling page
- Settings actions now preserve the active section after save/unmute flows
- Internal rich-text mention, hashtag, and notification URLs now consistently respect `TRUX_BASE_URL`

***

### Technical

- Extended `users` reads/updates to include `about_me`, `profile_links_json`, `show_likes_public`, and `show_bookmarks_public`
- Added profile helper logic in `src/profiles.php` for link normalization, provider detection, icon mapping, and privacy preference storage
- Added user activity fetch helpers in `src/posts.php` for authored replies, liked posts/comments, and bookmarked profile content
- Added new Settings layout/navigation behavior in `public/assets/style.css` and `public/assets/app.js`
- Verified syntax with `php -l` on the touched PHP files and `node --check public/assets/app.js`

***

## Omnicus v0.4.5 - Discovery UI Overhaul

**Branch**: Production
**Date**: 2026-03-15

***

### Added

- New dedicated Discovery module layout on the home feed
- Purpose-built Discovery style components in `public/assets/style.css` (`discoveryBlock`, `discoveryGrid`, `discoveryPane`)
- New `discoveryTag` card presentation for trending hashtags
- New `discoveryUser` row presentation with aligned follow actions
- Responsive Discovery behavior for tablet and mobile breakpoints

***

### Changed

- Discovery 1.0 section no longer uses settings-style list rendering
- Trending hashtags and suggested users now render in two structured content panes
- Follow actions in Discovery suggestions are now visually aligned and easier to scan
- Discovery content spacing, typography hierarchy, and card readability were improved

***

### Technical

- Updated Discovery markup in `public/index.php` to use semantic pane/list components
- Hardened follow redirect validation in `public/follow.php` to reject control-character payloads
- Verified syntax with `C:\\xampp\\php\\php.exe -l public/index.php` and `public/follow.php`

***

## Omnicus v0.4.1 - Discovery & Ranking Algorithm 1.0

**Branch**: Production
**Date**: 2026-03-15

***

### Added

- New discovery helper layer: `src/discovery.php`
- Trending hashtags module on the home feed
- Suggested users ("Who to follow") module on the home feed
- Optional safe redirect support in `public/follow.php` (`redirect` POST parameter)

***

### Changed

- The `For You` feed now uses Discovery Algorithm 1.0 instead of pure reverse-chronological order
- Discovery ranking now combines freshness, engagement, and social-proximity signals
- Home feed hero text and empty state now reflect discovery behavior

***

### Technical

- Added discovery bootstrap include in `public/_bootstrap.php`
- Discovery feed falls back to chronological feed if discovery-only tables are unavailable
- User suggestions now use mutual connections, follower momentum, and recent posting activity

***

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

