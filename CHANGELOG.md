# Omincus Updates (Beta Versions)
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

