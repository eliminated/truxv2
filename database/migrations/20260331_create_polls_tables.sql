-- Create polls, poll_options, and poll_votes tables for Post Polls feature

CREATE TABLE IF NOT EXISTS polls (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id    BIGINT UNSIGNED NOT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polls_post (post_id),
    INDEX idx_polls_created_at (created_at),
    CONSTRAINT fk_polls_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_options (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    poll_id    BIGINT UNSIGNED NOT NULL,
    body       VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_poll_options_order (poll_id, sort_order),
    INDEX idx_poll_options_poll (poll_id),
    CONSTRAINT fk_poll_options_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_votes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    poll_id        BIGINT UNSIGNED NOT NULL,
    poll_option_id BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_poll_votes_user (poll_id, user_id),
    INDEX idx_poll_votes_user (user_id, created_at),
    CONSTRAINT fk_poll_votes_poll   FOREIGN KEY (poll_id)        REFERENCES polls        (id) ON DELETE CASCADE,
    CONSTRAINT fk_poll_votes_option FOREIGN KEY (poll_option_id) REFERENCES poll_options (id) ON DELETE CASCADE,
    CONSTRAINT fk_poll_votes_user   FOREIGN KEY (user_id)        REFERENCES users        (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
