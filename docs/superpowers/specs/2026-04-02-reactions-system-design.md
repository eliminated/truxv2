# Reactions System Design

**Date:** 2026-04-02
**Status:** Approved
**Scope:** Posts, comments, and DM messages

---

## Overview

Replace the binary like system with a 6-reaction expressive system (Twitter/X-style). The existing like button remains as a quick-tap shortcut for ❤️. Hovering (desktop) or long-pressing (mobile) the like button reveals a picker with all 6 reactions. Works across posts, comments, and DM messages.

---

## Reactions

| Emoji | Slug |
|-------|------|
| ❤️ | `heart` |
| 🔥 | `fire` |
| 👏 | `clap` |
| 😂 | `laugh` |
| 🤔 | `think` |
| 💯 | `hundred` |

Slugs are stored in the DB (charset-safe). The display layer maps slugs to emoji.

---

## Database Schema

### New table: `reactions`

```sql
CREATE TABLE reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('post', 'comment', 'dm_message') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    reaction_type ENUM('heart', 'fire', 'clap', 'laugh', 'think', 'hundred') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (user_id, entity_type, entity_id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_user (user_id)
);
```

- One reaction per user per entity enforced by UNIQUE constraint.
- Changing reaction = UPDATE (not INSERT + DELETE).
- Clicking active reaction = removes it (toggle-off).

### Migration

Migration file: `database/migrations/20260402_reactions.sql`

1. Create `reactions` table
2. Migrate all `post_likes` rows → `reactions` as `entity_type='post', reaction_type='heart'`
3. Drop `post_likes`

```sql
INSERT INTO reactions (user_id, entity_type, entity_id, reaction_type, created_at)
SELECT user_id, 'post', post_id, 'heart', created_at
FROM post_likes;
```

No data is lost — every existing like becomes a ❤️ heart reaction.

---

## Backend

### New module: `src/reactions.php`

Four core functions:

**`add_or_update_reaction($user_id, $entity_type, $entity_id, $reaction_type): ?string`**
- First calls `get_user_reaction()` to check existing reaction
- If existing reaction matches new reaction → calls `remove_reaction()` and returns `null` (toggle-off)
- Otherwise uses `INSERT ... ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)` to set/change
- Returns new reaction slug, or `null` if removed

**`remove_reaction($user_id, $entity_type, $entity_id): void`**
- Deletes the reaction row for the given user + entity

**`get_reactions_for_entity($entity_type, $entity_id): array`**
- Returns counts grouped by reaction_type
- Example: `['heart' => 12, 'fire' => 5, 'laugh' => 2]`

**`get_user_reaction($user_id, $entity_type, $entity_id): ?string`**
- Returns user's current reaction slug for entity, or `null`

### New AJAX handler: `public/react.php`

Standard pattern:
1. Verify CSRF token
2. Check auth (`require_login()`)
3. Validate `entity_type` and `entity_id` (entity must exist)
4. Call `add_or_update_reaction()`
5. Fire notification if reaction is new (not a removal, not self-reaction)
6. Return JSON: `{ reaction_type, counts, user_reaction }`

### Retired: `public/like_post.php`

All like traffic routes through `react.php`. `like_post.php` is removed.

### Updated: `src/posts.php`

- Remove all references to `post_likes` table
- Like count queries replaced with `get_reactions_for_entity('post', $id)`
- `has_liked()` replaced with `get_user_reaction($user_id, 'post', $id) !== null`

---

## Notifications

New notification type: `'reaction'`

- Triggered in `react.php` after a successful new reaction
- Not triggered on reaction removal
- Not triggered when reacting to your own content
- Message format: `"{username} reacted 🔥 to your post"` / `"...to your comment"` / `"...to your message"` — entity type determines the suffix (slug mapped to emoji at render time)
- Hooks into existing `src/notifications.php` — new `create_notification()` call with type `'reaction'`

---

## Frontend

### Reaction Picker

A floating pill of 6 emoji buttons rendered in a row:
```
[ ❤️  🔥  👏  😂  🤔  💯 ]
```

**Trigger:**
- Desktop: hover over like button for 500ms → picker floats above button
- Mobile: long-press like button for 400ms → picker appears
- Quick tap: instantly toggles ❤️ heart (existing like behavior preserved)

**Behavior:**
- Clicking an emoji → calls `react.php`, closes picker, updates button state
- Clicking outside picker → dismisses without reacting
- Clicking active reaction → removes reaction (toggle-off)

**Animation:** Subtle scale-in on appear, scale-out on dismiss.

### Post Cards (`_post_actions_bar.php`)

The like button area is updated to show:
- Top 3 most-used reaction emoji + combined total count: `❤️🔥😂 24`
- User's active reaction replaces the like icon and is highlighted
- No active reaction → shows default heart outline (current like button look)

### Comments

- Same picker trigger on the comment action area
- Reaction counts render as emoji cluster beneath each comment
- Comment upvote/downvote system (for ranking) remains separate — reactions are expressive, not ranked

### DM Message Bubbles (`_dm_message_bubble.php`)

- Hover/long-press a message bubble → picker appears
- Reactions render as a small emoji cluster beneath the bubble
- Multiple users can react to the same message — counts stack per emoji
- Reactions fetched as part of the messages poll response

### JavaScript

- **`public/assets/app.js`** — picker logic for posts and comments (event delegation on `.like-btn`)
- **`public/assets/messages_v2.js`** — picker logic for DM message bubbles

No build tool. Vanilla JS throughout.

### CSS

New file: `public/assets/css/components/reactions.css`

- `.reaction-picker` — floating pill container
- `.reaction-btn` — individual emoji button (scale on hover)
- `.reaction-summary` — emoji + count display on cards
- `.reaction-btn.active` — highlighted state for user's current reaction

---

## Files Changed / Created

| File | Action |
|------|--------|
| `database/migrations/20260402_reactions.sql` | Create |
| `src/reactions.php` | Create |
| `public/react.php` | Create |
| `public/like_post.php` | Delete |
| `src/posts.php` | Update (remove post_likes refs) |
| `src/notifications.php` | Update (add reaction type) |
| `public/_post_actions_bar.php` | Update (picker + reaction display) |
| `public/_post_card.php` | Update (reaction counts in card) |
| `public/_dm_message_bubble.php` | Update (picker + reaction display) |
| `public/assets/app.js` | Update (picker JS for posts/comments) |
| `public/assets/messages_v2.js` | Update (picker JS for DMs) |
| `public/assets/css/components/reactions.css` | Create |
| `public/assets/css/main.css` | Update (import reactions.css) |
| `public/_bootstrap.php` | Update (require `src/reactions.php`) |

---

## Success Criteria

- Quick-tap like button still works as ❤️ reaction
- Hover/long-press reveals picker with all 6 reactions
- One reaction per user per entity; changing reaction updates correctly
- Reactions work on posts, comments, and DM messages
- All existing likes migrated to heart reactions with no data loss
- Notifications sent for all new reactions (not removals, not self)
- Reaction counts display correctly on post cards and message bubbles
