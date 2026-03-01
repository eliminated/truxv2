# Omincus Updates (Beta Versions)
## Omnicus v0.1 - Initial Release
Initial Releases

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
