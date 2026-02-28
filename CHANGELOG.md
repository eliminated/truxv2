# Omincus Updates (Beta Versions)
## Omnicus v0.1 - Initial Release
Initial Releases

## Omnicus v0.2.0 — UI Overhaul & Follow System

**Branch**: Beta
****Date**: 2026-02-28

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