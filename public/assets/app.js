(function () {
  // Autosize textareas a bit on input
  document.addEventListener("input", (e) => {
    const el = e.target;
    if (el && el.tagName === "TEXTAREA") {
      el.style.height = "auto";
      el.style.height = Math.min(el.scrollHeight, 420) + "px";
    }
  });

  // Confirm dangerous actions (delete)
  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const msg = form.getAttribute("data-confirm");
    if (msg) {
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    }
  });
})();

(() => {
  const INPUT_SELECTOR = "textarea[data-mention-input='1']";

  const esc = (value) =>
    String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");

  const autosize = (textarea) => {
    textarea.style.height = "auto";
    textarea.style.height = Math.min(textarea.scrollHeight, 420) + "px";
  };

  const extractMentionContext = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return null;
    if (textarea.selectionStart !== textarea.selectionEnd) return null;

    const caret = textarea.selectionStart ?? 0;
    const before = textarea.value.slice(0, caret);
    const match = before.match(/(?:^|\s)@([A-Za-z0-9_]*)$/);
    if (!match) return null;

    const query = String(match[1] || "");
    if (!query || !/^[A-Za-z0-9_]{1,32}$/.test(query)) {
      return null;
    }

    const atIndex = before.lastIndexOf("@");
    if (atIndex < 0) return null;

    return {
      query,
      start: atIndex,
      end: caret,
    };
  };

  const initMentionInput = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    if (textarea.dataset.mentionReady === "1") return;
    textarea.dataset.mentionReady = "1";

    const host = textarea.closest(".field") || textarea.parentElement;
    if (!(host instanceof HTMLElement)) return;

    const panel = document.createElement("div");
    panel.className = "mentionSuggest";
    panel.hidden = true;
    panel.setAttribute("data-mention-panel", "1");
    host.insertBefore(panel, textarea);

    const state = {
      items: [],
      activeIndex: -1,
      context: null,
      requestId: 0,
      abortController: null,
    };

    const hide = () => {
      panel.hidden = true;
      panel.innerHTML = "";
      state.items = [];
      state.activeIndex = -1;
      state.context = null;
    };

    const applySelection = (username) => {
      if (!state.context) return;
      const replacement = `@${username} `;
      const value = textarea.value;
      textarea.value =
        value.slice(0, state.context.start) +
        replacement +
        value.slice(state.context.end);

      const nextCaret = state.context.start + replacement.length;
      textarea.focus();
      textarea.setSelectionRange(nextCaret, nextCaret);
      autosize(textarea);
      hide();
    };

    const render = (items, query, preserveIndex = false) => {
      state.items = Array.isArray(items) ? items : [];
      if (state.items.length === 0) {
        state.activeIndex = -1;
      } else if (!preserveIndex || state.activeIndex < 0 || state.activeIndex >= state.items.length) {
        state.activeIndex = 0;
      }

      if (query === "") {
        hide();
        return;
      }

      panel.hidden = false;

      if (state.items.length === 0) {
        panel.innerHTML = '<div class="mentionSuggest__empty">No matching users.</div>';
        return;
      }

      panel.innerHTML = `
        <div class="mentionSuggest__label">Mention someone</div>
        <div class="mentionSuggest__list">
          ${state.items
            .map(
              (item, index) => `
                <button
                  class="mentionSuggest__item${index === state.activeIndex ? " is-active" : ""}"
                  type="button"
                  data-mention-option="1"
                  data-mention-index="${index}"
                  data-mention-username="${esc(item.username || "")}">
                  <span class="mentionSuggest__name">@${esc(item.username || "")}</span>
                </button>
              `
            )
            .join("")}
        </div>
      `;
    };

    const fetchSuggestions = async () => {
      const context = extractMentionContext(textarea);
      state.context = context;
      if (!context) {
        hide();
        return;
      }

      state.requestId += 1;
      const currentRequestId = state.requestId;
      if (state.abortController instanceof AbortController) {
        state.abortController.abort();
      }

      const controller = new AbortController();
      state.abortController = controller;

      try {
        const res = await fetch(
          `/mention_suggestions.php?q=${encodeURIComponent(context.query)}`,
          {
            headers: { Accept: "application/json" },
            signal: controller.signal,
          }
        );
        const data = await res.json();
        if (currentRequestId !== state.requestId) return;
        if (!res.ok || !data.ok) {
          hide();
          return;
        }

        render(Array.isArray(data.users) ? data.users : [], context.query);
      } catch (err) {
        if (err instanceof DOMException && err.name === "AbortError") {
          return;
        }
        hide();
      }
    };

    textarea.addEventListener("input", () => {
      void fetchSuggestions();
    });

    textarea.addEventListener("click", () => {
      void fetchSuggestions();
    });

    textarea.addEventListener("focus", () => {
      void fetchSuggestions();
    });

    textarea.addEventListener("keyup", (e) => {
      if (["ArrowUp", "ArrowDown", "Enter", "Tab", "Escape"].includes(e.key)) {
        return;
      }
      void fetchSuggestions();
    });

    textarea.addEventListener("keydown", (e) => {
      if (panel.hidden || state.items.length === 0) return;

      if (e.key === "ArrowDown") {
        e.preventDefault();
        state.activeIndex = (state.activeIndex + 1) % state.items.length;
        render(state.items, state.context?.query || "", true);
        return;
      }

      if (e.key === "ArrowUp") {
        e.preventDefault();
        state.activeIndex =
          (state.activeIndex - 1 + state.items.length) % state.items.length;
        render(state.items, state.context?.query || "", true);
        return;
      }

      if (e.key === "Enter" || e.key === "Tab") {
        if (state.activeIndex < 0 || !state.items[state.activeIndex]) return;
        e.preventDefault();
        applySelection(String(state.items[state.activeIndex].username || ""));
        return;
      }

      if (e.key === "Escape") {
        e.preventDefault();
        hide();
      }
    });

    panel.addEventListener("mousedown", (e) => {
      e.preventDefault();
    });

    panel.addEventListener("click", (e) => {
      const option = e.target.closest("[data-mention-option='1']");
      if (!(option instanceof HTMLButtonElement)) return;
      const username = option.getAttribute("data-mention-username") || "";
      if (username) {
        applySelection(username);
      }
    });

    document.addEventListener("click", (e) => {
      if (!(e.target instanceof Node)) return;
      if (e.target === textarea || panel.contains(e.target)) return;
      hide();
    });
  };

  document.querySelectorAll(INPUT_SELECTOR).forEach((textarea) => {
    initMentionInput(textarea);
  });
})();

(() => {
  const TIME_SELECTOR = "[data-time-ago='1'][data-time-source]";

  const parseTimeSource = (raw) => {
    if (!raw || typeof raw !== "string") return null;
    const source = raw.trim();
    if (!source) return null;

    // Parse MySQL-like local datetime: YYYY-MM-DD HH:MM:SS
    const m = source.match(
      /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/
    );
    if (m) {
      const y = Number(m[1]);
      const mo = Number(m[2]) - 1;
      const d = Number(m[3]);
      const h = Number(m[4] || "0");
      const mi = Number(m[5] || "0");
      const s = Number(m[6] || "0");
      const dt = new Date(y, mo, d, h, mi, s);
      return Number.isNaN(dt.getTime()) ? null : dt;
    }

    const dt = new Date(source);
    return Number.isNaN(dt.getTime()) ? null : dt;
  };

  const formatAgo = (diffSec) => {
    if (diffSec < 0) diffSec = 0;
    if (diffSec < 10) return "just now";
    if (diffSec < 60) return `${diffSec} seconds ago`;

    const mins = Math.floor(diffSec / 60);
    if (mins < 60) return `${mins} minute${mins === 1 ? "" : "s"} ago`;

    const hours = Math.floor(diffSec / 3600);
    if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;

    const days = Math.floor(diffSec / 86400);
    if (days < 7) return `${days} day${days === 1 ? "" : "s"} ago`;

    const weeks = Math.floor(days / 7);
    if (weeks < 5) return `${weeks} week${weeks === 1 ? "" : "s"} ago`;

    const months = Math.floor(days / 30);
    if (months < 12) return `${months} month${months === 1 ? "" : "s"} ago`;

    const years = Math.floor(days / 365);
    return `${years} year${years === 1 ? "" : "s"} ago`;
  };

  const refreshTimeAgo = () => {
    const nowMs = Date.now();
    document.querySelectorAll(TIME_SELECTOR).forEach((el) => {
      const source = el.getAttribute("data-time-source") || "";
      const dt = parseTimeSource(source);
      if (!dt) return;

      const diffSec = Math.floor((nowMs - dt.getTime()) / 1000);
      el.textContent = formatAgo(diffSec);
    });
  };

  refreshTimeAgo();
  window.setInterval(refreshTimeAgo, 30000);
  window.addEventListener("trux:times:refresh", refreshTimeAgo);
  window.truxRefreshTimeAgo = refreshTimeAgo;
})();

(() => {
  const fx = document.getElementById("pageFX");
  const classicAppearance = document.body.classList.contains("appearance--classic");
  const reducedBySetting = document.body.classList.contains("motion--reduced");
  const reducedBySystem = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const shouldReduceMotion = classicAppearance || reducedBySetting || reducedBySystem;

  // Fade-in page content on load
  const main = document.querySelector("main");
  if (main) main.classList.add("page-enter");

  if (shouldReduceMotion) {
    if (main) main.classList.remove("page-enter");
    if (fx) fx.classList.remove("is-active");
    return;
  }

  if (!fx) return;

  const shouldHandleLink = (a) => {
    if (!a) return false;
    const href = a.getAttribute("href");
    if (!href) return false;
    if (href.startsWith("#")) return false;
    if (a.target && a.target !== "_self") return false;
    if (a.hasAttribute("download")) return false;
    if (a.dataset.noFx === "1") return false;

    // external link? (different origin)
    try {
      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return false;
    } catch {
      return false;
    }

    return true;
  };

  const activate = () => {
    fx.classList.add("is-active");
  };

  // Intercept internal navigation clicks to show transition
  document.addEventListener("click", (e) => {
    const a = e.target.closest("a");
    if (!shouldHandleLink(a)) return;

    // Let ctrl/cmd click open new tab normally
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    e.preventDefault();
    activate();

    const href = a.getAttribute("href");
    // Navigate shortly after animation starts
    window.setTimeout(() => {
      window.location.href = href;
    }, 120);
  });

  // Show transition on form submit too (optional)
  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    // Skip JS-handled submits
    if (e.defaultPrevented) return;
    // Skip if it opens in new tab
    if (form.target && form.target !== "_self") return;
    // Explicit opt-out
    if (form.dataset.noFx === "1") return;

    activate();
  });

  // If user hits back/forward, remove overlay
  window.addEventListener("pageshow", () => {
    fx.classList.remove("is-active");
  });
})();

(() => {
  const dock = document.getElementById("commentDock");
  if (!dock) return;

  const postPane = dock.querySelector("[data-comment-post]");
  const list = dock.querySelector("[data-comment-list]");
  const listWrap = dock.querySelector(".commentDock__listWrap");
  const empty = dock.querySelector("[data-comment-empty]");
  const form = dock.querySelector("[data-comment-form]");
  const postIdField = dock.querySelector("[data-comment-post-id]");
  const parentIdField = dock.querySelector("[data-comment-parent-id]");
  const replyUserIdField = dock.querySelector("[data-comment-reply-user-id]");
  const replyingBox = dock.querySelector("[data-comment-replying]");
  const replyingUser = dock.querySelector("[data-comment-replying-user]");
  const openPostLink = dock.querySelector("[data-comment-open-post]");

  let currentPostId = 0;
  let lastTrigger = null;

  const esc = (value) =>
    String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");

  const readJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { ok: false, error: text || "Request failed." };
    }
  };

  const bodyToHtml = (value) => esc(value).replaceAll("\n", "<br>");
  const getPostBodyHtml = (bodyHtml, fallbackBody) => {
    if (typeof bodyHtml === "string" && bodyHtml !== "") {
      return bodyHtml;
    }
    return bodyToHtml(fallbackBody || "");
  };
  const commentScoreClass = (score) =>
    score < 0 ? " is-negative" : score > 0 ? " is-positive" : "";

  const editedMetaMarkup = (editedAt, editedTimeAgo, editedExactTime) => {
    if (!editedAt) return "";
    return `
      <span class="editedMeta">
        <span class="editedMeta__label">EDITED AT</span>
        <span
          class="editedMeta__time"
          data-time-ago="1"
          data-time-source="${esc(editedAt)}"
          title="${esc(editedExactTime || editedAt)}">${esc(editedTimeAgo || editedAt)}</span>
      </span>
    `;
  };

  const getCsrfToken = () => {
    const input = document.querySelector("input[name='_csrf']");
    return input instanceof HTMLInputElement ? input.value : "";
  };

  const ownerMenuMarkup = (entityType, entityId) => `
    <div class="contentMenu" data-content-menu="1">
      <button
        class="contentMenu__trigger"
        type="button"
        aria-label="Open ${esc(entityType)} actions"
        data-content-menu-trigger="1">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
        </svg>
      </button>
      <div class="contentMenu__panel" role="menu" aria-label="${esc(entityType)} actions">
        <button
          class="contentMenu__item"
          type="button"
          role="menuitem"
          data-owner-edit="1"
          data-owner-type="${esc(entityType)}"
          data-owner-id="${entityId}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 20h4.2l9.8-9.8-4.2-4.2L4 15.8V20Zm11.1-13.9 4.2 4.2 1.4-1.4a1.5 1.5 0 0 0 0-2.1l-2.1-2.1a1.5 1.5 0 0 0-2.1 0l-1.4 1.4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          </svg>
          <span>Edit</span>
        </button>
        <button
          class="contentMenu__item contentMenu__item--placeholder"
          type="button"
          role="menuitem"
          aria-disabled="true">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m7 5 5-2 5 2v14l-5-2-5 2V5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          </svg>
          <span>Bookmark</span>
        </button>
        <button
          class="contentMenu__item contentMenu__item--danger"
          type="button"
          role="menuitem"
          data-owner-delete="1"
          data-owner-type="${esc(entityType)}"
          data-owner-id="${entityId}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Delete</span>
        </button>
      </div>
    </div>
  `;

  const closeAllContentMenus = (exceptMenu) => {
    document.querySelectorAll("[data-content-menu='1']").forEach((menu) => {
      if (!(menu instanceof HTMLElement)) return;
      if (exceptMenu && menu === exceptMenu) return;
      menu.classList.remove("is-open");
      const post = menu.closest(".post");
      if (post instanceof HTMLElement) {
        post.classList.remove("post--menuOpen");
      }
    });
  };

  const updatePostBodies = (postId, bodyHtml, fallbackBody = "") => {
    document
      .querySelectorAll(`.post[data-post-id="${postId}"] .post__body`)
      .forEach((node) => {
        node.innerHTML = getPostBodyHtml(bodyHtml, fallbackBody);
      });
  };

  const setPostEditedState = (postId, editedAt, editedTimeAgo, editedExactTime) => {
    document
      .querySelectorAll(`.post[data-post-id="${postId}"] .post__subRow`)
      .forEach((row) => {
        if (!(row instanceof HTMLElement)) return;

        let node = row.querySelector(`[data-post-edited-for="${postId}"]`);
        if (!editedAt) {
          if (node instanceof HTMLElement) node.remove();
          return;
        }

        if (!(node instanceof HTMLElement)) {
          node = document.createElement("span");
          node.className = "editedMeta";
          node.setAttribute("data-post-edited-for", String(postId));
          const dot = row.querySelector(".post__dot");
          if (dot instanceof HTMLElement) {
            row.insertBefore(node, dot);
          } else {
            row.appendChild(node);
          }
        }

        node.innerHTML = `
          <span class="editedMeta__label">EDITED AT</span>
          <span
            class="editedMeta__time"
            data-time-ago="1"
            data-time-source="${esc(editedAt)}"
            title="${esc(editedExactTime || editedAt)}">${esc(editedTimeAgo || editedAt)}</span>
        `;
      });
    window.dispatchEvent(new CustomEvent("trux:times:refresh"));
  };

  const removePostCards = (postId) => {
    const posts = Array.from(document.querySelectorAll(`.post[data-post-id="${postId}"]`));
    if (posts.length === 0) return;

    const singlePost = posts.find(
      (node) => node instanceof HTMLElement && node.classList.contains("post--single")
    );
    const parent = singlePost instanceof HTMLElement ? singlePost.parentElement : null;

    if (parent && singlePost instanceof HTMLElement) {
      const flash = document.createElement("div");
      flash.className = "flash flash--success";
      flash.innerHTML = `Post deleted. <a href="/" data-no-fx="1">Back to feed</a>`;
      parent.insertBefore(flash, singlePost);
    }

    posts.forEach((node) => node.remove());
  };

  const isOpen = () => !dock.hasAttribute("hidden");

  const setCommentCount = (postId, count) => {
    const nodes = document.querySelectorAll(`[data-comment-count-for="${postId}"]`);
    nodes.forEach((n) => {
      n.textContent = String(count);
    });
  };

  const setCommentVoteState = (commentId, score, viewerVote) => {
    document
      .querySelectorAll(`[data-comment-score-for="${commentId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        node.textContent = String(score);
        node.classList.toggle("is-positive", score > 0);
        node.classList.toggle("is-negative", score < 0);
      });

    document
      .querySelectorAll(`[data-comment-vote="1"][data-comment-id="${commentId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        const voteValue = Number(node.getAttribute("data-vote-value") || "0");
        node.classList.toggle("is-active", voteValue === viewerVote);
      });
  };

  const setReplyState = (commentId, userId, username) => {
    if (!(form instanceof HTMLFormElement)) return;
    const textarea = form.querySelector("textarea[name='body']");

    if (parentIdField) parentIdField.value = String(commentId);
    if (replyUserIdField) replyUserIdField.value = String(userId);
    if (replyingUser) replyingUser.textContent = `@${username}`;
    if (replyingBox) replyingBox.hidden = false;

    if (textarea instanceof HTMLTextAreaElement) {
      const mention = `@${username} `;
      const withoutLeadingMention = textarea.value.replace(/^@\S+\s+/, "").trimStart();
      textarea.value = `${mention}${withoutLeadingMention}`;
      textarea.focus();
      textarea.style.height = "auto";
      textarea.style.height = Math.min(textarea.scrollHeight, 420) + "px";
    }
  };

  const clearReplyState = () => {
    if (parentIdField) parentIdField.value = "";
    if (replyUserIdField) replyUserIdField.value = "";
    if (replyingUser) replyingUser.textContent = "";
    if (replyingBox) replyingBox.hidden = true;
  };

  const buildCommentTree = (comments) => {
    const byId = new Map();
    const roots = [];

    comments.forEach((raw) => {
      const c = {
        ...raw,
        id: Number(raw.id || 0),
        parent_comment_id: raw.parent_comment_id ? Number(raw.parent_comment_id) : 0,
        user_id: Number(raw.user_id || 0),
        children: [],
      };
      byId.set(c.id, c);
    });

    byId.forEach((c) => {
      if (c.parent_comment_id > 0 && byId.has(c.parent_comment_id)) {
        byId.get(c.parent_comment_id).children.push(c);
      } else {
        roots.push(c);
      }
    });

    return roots;
  };

  const renderCommentNode = (c) => {
    const item = document.createElement("article");
    item.className = "commentDock__item";
    item.dataset.commentId = String(c.id);
    const commentBodyHtml =
      typeof c.body_html === "string" && c.body_html !== ""
        ? c.body_html
        : bodyToHtml(c.body || "");
    item.innerHTML = `
      <div class="commentDock__meta">
        <div class="commentDock__author">
          <a class="commentDock__avatar" href="/profile.php?u=${encodeURIComponent(c.username)}" aria-label="View @${esc(c.username)} profile">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 12a4.2 4.2 0 1 0-4.2-4.2A4.2 4.2 0 0 0 12 12Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" fill="currentColor" />
            </svg>
          </a>
          <a class="commentDock__user" href="/profile.php?u=${encodeURIComponent(c.username)}">@${esc(c.username)}</a>
        </div>
        <div class="commentDock__metaEnd">
          <span
            class="commentDock__time"
            data-time-ago="1"
            data-time-source="${esc(c.created_at)}"
            title="${esc(c.exact_time)}">${esc(c.time_ago)}</span>
          ${editedMetaMarkup(c.edited_at, c.edited_time_ago, c.edited_exact_time)}
          ${c.is_owner ? ownerMenuMarkup("comment", c.id) : ""}
        </div>
      </div>
      <div class="commentDock__body">${commentBodyHtml}</div>
      <div class="commentDock__actions">
        <div class="commentDock__voteGroup" aria-label="Comment votes">
          <button
            type="button"
            class="commentDock__voteBtn commentDock__voteBtn--up${c.viewer_vote === 1 ? " is-active" : ""}"
            data-comment-vote="1"
            data-comment-id="${c.id}"
            data-vote-value="1"
            aria-label="Upvote comment">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 5 5.5 13h4.1v6h4.8v-6h4.1L12 5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
          </button>
          <span class="commentDock__score${commentScoreClass(Number(c.score || 0))}" data-comment-score-for="${c.id}">${Number(c.score || 0)}</span>
          <button
            type="button"
            class="commentDock__voteBtn commentDock__voteBtn--down${c.viewer_vote === -1 ? " is-active" : ""}"
            data-comment-vote="1"
            data-comment-id="${c.id}"
            data-vote-value="-1"
            aria-label="Downvote comment">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m12 19 6.5-8h-4.1V5H9.6v6H5.5L12 19Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
        <button
          type="button"
          class="commentDock__replyBtn"
          data-comment-reply="1"
          data-comment-id="${c.id}"
          data-comment-user-id="${c.user_id}"
          data-comment-username="${esc(c.username)}">Reply</button>
      </div>
    `;

    if (Array.isArray(c.children) && c.children.length > 0) {
      const childrenWrap = document.createElement("div");
      childrenWrap.className = "commentDock__children";
      c.children.forEach((child) => {
        childrenWrap.appendChild(renderCommentNode(child));
      });
      item.appendChild(childrenWrap);
    }

    return item;
  };

  const renderComments = (comments) => {
    if (!list || !empty) return;
    list.innerHTML = "";

    if (!Array.isArray(comments) || comments.length === 0) {
      empty.hidden = false;
      return;
    }

    empty.hidden = true;
    const roots = buildCommentTree(comments);
    roots.forEach((c) => list.appendChild(renderCommentNode(c)));
    window.dispatchEvent(new CustomEvent("trux:times:refresh"));
  };

  const loadComments = async (postId) => {
    if (!list) return;
    list.innerHTML = `<div class="muted">Loading comments...</div>`;
    if (empty) empty.hidden = true;

    try {
      const res = await fetch(`/post_comments.php?id=${postId}`, {
        headers: { Accept: "application/json" },
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || "Could not load comments.");

      renderComments(data.comments || []);
      setCommentCount(postId, Number(data.count || 0));
      if (listWrap instanceof HTMLElement) {
        listWrap.scrollTop = listWrap.scrollHeight;
      }
    } catch (err) {
      list.innerHTML = `<div class="flash flash--error">Could not load comments.</div>`;
      if (empty) empty.hidden = true;
      console.error(err);
    }
  };

  const fillPostPane = (trigger, postId) => {
    if (!postPane) return;
    postPane.innerHTML = "";

    const sourcePost =
      trigger.closest(".post") || document.querySelector(`.post[data-post-id="${postId}"]`);
    if (!sourcePost) {
      postPane.innerHTML = `<div class="muted">Post preview unavailable.</div>`;
      return;
    }

    const clone = sourcePost.cloneNode(true);
    clone.classList.add("commentDock__postPreview");

    clone.querySelectorAll(".post__actionsBar, .post__actions").forEach((el) => el.remove());
    clone.querySelectorAll("[data-comment-open]").forEach((el) => {
      el.setAttribute("disabled", "disabled");
      el.setAttribute("aria-disabled", "true");
    });

    postPane.appendChild(clone);
  };

  const openDock = (trigger) => {
    const postId = Number(trigger.getAttribute("data-post-id") || "0");
    if (!postId) return;

    currentPostId = postId;
    lastTrigger = trigger;

    if (postIdField) postIdField.value = String(postId);
    if (openPostLink) {
      const href = trigger.getAttribute("data-post-url") || `/post.php?id=${postId}`;
      openPostLink.setAttribute("href", href);
    }

    fillPostPane(trigger, postId);
    dock.removeAttribute("hidden");
    document.body.classList.add("commentDock-open");
    clearReplyState();
    loadComments(postId);

    const input = form ? form.querySelector("textarea[name='body']") : null;
    if (input instanceof HTMLTextAreaElement) {
      input.value = "";
      window.setTimeout(() => input.focus(), 30);
    }
  };

  const closeDock = () => {
    if (!isOpen()) return;
    dock.setAttribute("hidden", "hidden");
    document.body.classList.remove("commentDock-open");
    currentPostId = 0;
    if (postIdField) postIdField.value = "";
    clearReplyState();
    if (lastTrigger && typeof lastTrigger.focus === "function") {
      lastTrigger.focus();
    }
  };

  document.addEventListener("click", async (e) => {
    if (!(e.target instanceof Element)) return;

    const menuTrigger = e.target.closest("[data-content-menu-trigger='1']");
    if (menuTrigger instanceof HTMLButtonElement) {
      e.preventDefault();
      const menu = menuTrigger.closest("[data-content-menu='1']");
      if (!(menu instanceof HTMLElement)) return;
      const willOpen = !menu.classList.contains("is-open");
      closeAllContentMenus(willOpen ? menu : null);
      menu.classList.toggle("is-open", willOpen);
      const post = menu.closest(".post");
      if (post instanceof HTMLElement) {
        post.classList.toggle("post--menuOpen", willOpen);
      }
      return;
    }

    const ownerEditBtn = e.target.closest("[data-owner-edit='1']");
    if (ownerEditBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      closeAllContentMenus();

      const entityType = ownerEditBtn.getAttribute("data-owner-type") || "";
      const entityId = Number(ownerEditBtn.getAttribute("data-owner-id") || "0");
      const csrf = getCsrfToken();
      if (!entityType || !entityId || !csrf) {
        window.alert("Action unavailable right now.");
        return;
      }

      const isPost = entityType === "post";
      const sourceNode = isPost
        ? document.querySelector(`.post[data-post-id="${entityId}"] .post__body`)
        : document.querySelector(`[data-comment-id="${entityId}"] .commentDock__body`);
      const currentBody = sourceNode instanceof HTMLElement ? sourceNode.innerText.trim() : "";
      const nextBody = window.prompt(
        isPost ? "Edit your post" : "Edit your comment",
        currentBody
      );
      if (nextBody === null) return;

      const trimmedBody = nextBody.trim();
      if (!trimmedBody) {
        window.alert(isPost ? "Post cannot be empty." : "Comment cannot be empty.");
        return;
      }

      const endpoint = isPost ? "/edit_post.php?format=json" : "/edit_comment.php?format=json";
      try {
        const res = await fetch(endpoint, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: new URLSearchParams({
            _csrf: csrf,
            id: String(entityId),
            body: trimmedBody,
          }),
        });
        const data = await readJson(res);
        if (!res.ok || !data.ok) {
          throw new Error(data.error || "Could not save changes.");
        }

        if (isPost) {
          updatePostBodies(
            entityId,
            String(data.body_html || ""),
            String(data.body || trimmedBody)
          );
          setPostEditedState(
            entityId,
            String(data.edited_at || ""),
            String(data.edited_time_ago || ""),
            String(data.edited_exact_time || "")
          );
        } else {
          await loadComments(Number(data.post_id || currentPostId || 0));
        }
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not save changes.");
      }
      return;
    }

    const ownerDeleteBtn = e.target.closest("[data-owner-delete='1']");
    if (ownerDeleteBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      closeAllContentMenus();

      const entityType = ownerDeleteBtn.getAttribute("data-owner-type") || "";
      const entityId = Number(ownerDeleteBtn.getAttribute("data-owner-id") || "0");
      const csrf = getCsrfToken();
      if (!entityType || !entityId || !csrf) {
        window.alert("Action unavailable right now.");
        return;
      }

      const isPost = entityType === "post";
      const confirmed = window.confirm(
        isPost ? "Delete this post?" : "Delete this comment?"
      );
      if (!confirmed) return;

      const endpoint = isPost ? "/delete_post.php?format=json" : "/delete_comment.php?format=json";
      try {
        const res = await fetch(endpoint, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: new URLSearchParams({
            _csrf: csrf,
            id: String(entityId),
          }),
        });
        const data = await readJson(res);
        if (!res.ok || !data.ok) {
          throw new Error(data.error || "Could not delete.");
        }

        if (isPost) {
          if (currentPostId === entityId) {
            lastTrigger = null;
            closeDock();
          }
          removePostCards(entityId);
        } else {
          if (typeof data.comments_count === "number") {
            setCommentCount(Number(data.post_id || currentPostId || 0), data.comments_count);
          }
          await loadComments(Number(data.post_id || currentPostId || 0));
        }
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not delete.");
      }
      return;
    }

    if (!e.target.closest("[data-content-menu='1']")) {
      closeAllContentMenus();
    }

    const openBtn = e.target.closest("[data-comment-open]");
    if (openBtn instanceof HTMLElement) {
      e.preventDefault();
      openDock(openBtn);
      return;
    }

    const closeBtn = e.target.closest("[data-comment-close]");
    if (closeBtn instanceof HTMLElement) {
      e.preventDefault();
      closeDock();
      return;
    }

    const replyBtn = e.target.closest("[data-comment-reply]");
    if (replyBtn instanceof HTMLElement) {
      e.preventDefault();
      const commentId = Number(replyBtn.getAttribute("data-comment-id") || "0");
      const userId = Number(replyBtn.getAttribute("data-comment-user-id") || "0");
      const username = replyBtn.getAttribute("data-comment-username") || "";
      if (commentId > 0 && userId > 0 && username !== "") {
        setReplyState(commentId, userId, username);
      }
      return;
    }

    const voteBtn = e.target.closest("[data-comment-vote='1']");
    if (voteBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      const commentId = Number(voteBtn.getAttribute("data-comment-id") || "0");
      const voteValue = Number(voteBtn.getAttribute("data-vote-value") || "0");
      if (!commentId || ![-1, 1].includes(voteValue)) return;

      const csrf = getCsrfToken();
      if (!csrf) {
        window.location.href = "/login.php";
        return;
      }

      const relatedButtons = document.querySelectorAll(`[data-comment-vote="1"][data-comment-id="${commentId}"]`);
      relatedButtons.forEach((node) => {
        if (node instanceof HTMLButtonElement) node.disabled = true;
      });

      try {
        const res = await fetch("/vote_comment.php?format=json", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: new URLSearchParams({
            _csrf: csrf,
            id: String(commentId),
            vote: String(voteValue),
          }),
        });
        const data = await readJson(res);
        if (res.status === 401 && data.login_url) {
          window.location.href = String(data.login_url);
          return;
        }
        if (!res.ok || !data.ok) {
          throw new Error(data.error || "Could not vote on comment.");
        }

        setCommentVoteState(commentId, Number(data.score || 0), Number(data.viewer_vote || 0));
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not vote on comment.");
      } finally {
        relatedButtons.forEach((node) => {
          if (node instanceof HTMLButtonElement) node.disabled = false;
        });
      }
      return;
    }

    const replyCancelBtn = e.target.closest("[data-comment-reply-cancel]");
    if (replyCancelBtn instanceof HTMLElement) {
      e.preventDefault();
      clearReplyState();
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeAllContentMenus();
      closeDock();
    }
  });

  if (form instanceof HTMLFormElement) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!currentPostId) return;

      const textarea = form.querySelector("textarea[name='body']");
      if (!(textarea instanceof HTMLTextAreaElement)) return;

      const body = textarea.value.trim();
      if (!body) return;

      const submitBtn = form.querySelector("button[type='submit']");
      if (submitBtn instanceof HTMLButtonElement) submitBtn.disabled = true;

      try {
        const fd = new FormData(form);
        fd.set("id", String(currentPostId));

        const res = await fetch("/comment_post.php?format=json", {
          method: "POST",
          body: fd,
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(data.error || "Could not add comment.");
        }

        textarea.value = "";
        textarea.style.height = "auto";
        clearReplyState();

        if (typeof data.comments_count === "number") {
          setCommentCount(currentPostId, data.comments_count);
        }
        await loadComments(currentPostId);
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not add comment.");
      } finally {
        if (submitBtn instanceof HTMLButtonElement) submitBtn.disabled = false;
      }
    });
  }
})();

(() => {
  const toJsonUrl = (action) => {
    const url = new URL(action, window.location.origin);
    url.searchParams.set("format", "json");
    return url.toString();
  };

  const readJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { ok: false, error: text || "Request failed." };
    }
  };

  const setActionCount = (kind, postId, count) => {
    const selectorByKind = {
      like: `[data-like-count-for="${postId}"]`,
      share: `[data-share-count-for="${postId}"]`,
      comment: `[data-comment-count-for="${postId}"]`,
    };
    const selector = selectorByKind[kind];
    if (!selector) return;
    document.querySelectorAll(selector).forEach((el) => {
      el.textContent = String(count);
    });
  };

  const setActionActive = (kind, postId, active) => {
    document
      .querySelectorAll(`form[data-ajax-action="1"][data-action-kind="${kind}"][data-post-id="${postId}"] .postAct`)
      .forEach((btn) => {
        btn.classList.toggle("is-active", !!active);
        if (kind === "like") {
          btn.setAttribute("aria-label", active ? "Unlike post" : "Like post");
        } else if (kind === "share") {
          btn.setAttribute("aria-label", active ? "Unshare post" : "Share post");
        }
      });
  };

  const setActionLoading = (form, loading) => {
    const btn = form.querySelector("button[type='submit']");
    if (!(btn instanceof HTMLButtonElement)) return;
    btn.disabled = loading;
    btn.classList.toggle("is-loading", loading);
  };

  document.addEventListener("submit", async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.ajaxAction !== "1") return;

    e.preventDefault();
    const kind = form.dataset.actionKind || "";
    const postId = Number(form.dataset.postId || "0");
    if (!kind || !postId) return;

    setActionLoading(form, true);
    try {
      const fd = new FormData(form);
      const res = await fetch(toJsonUrl(form.action), {
        method: "POST",
        body: fd,
        headers: { Accept: "application/json" },
      });
      const data = await readJson(res);
      if (!res.ok || !data.ok) {
        throw new Error(data.error || "Action failed.");
      }

      if (kind === "like") {
        if (typeof data.likes_count === "number") {
          setActionCount("like", postId, data.likes_count);
        }
        setActionActive("like", postId, !!data.liked);
      } else if (kind === "share") {
        if (typeof data.shares_count === "number") {
          setActionCount("share", postId, data.shares_count);
        }
        setActionActive("share", postId, !!data.shared);
      }
    } catch (err) {
      window.alert(err instanceof Error ? err.message : "Action failed.");
    } finally {
      setActionLoading(form, false);
    }
  });
})();

(() => {
  const form = document.querySelector("form[data-ajax-new-post='1']");
  if (!(form instanceof HTMLFormElement)) return;

  const readJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { ok: false, error: text || "Request failed." };
    }
  };

  const findOrCreateFlash = () => {
    let flash = form.parentElement ? form.parentElement.querySelector("[data-ajax-flash='1']") : null;
    if (flash instanceof HTMLElement) return flash;
    flash = document.createElement("div");
    flash.setAttribute("data-ajax-flash", "1");
    form.parentElement?.insertBefore(flash, form);
    return flash;
  };

  const setFlash = (type, html) => {
    const flash = findOrCreateFlash();
    flash.className = `flash flash--${type}`;
    flash.innerHTML = html;
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const submitBtn = form.querySelector("button[type='submit']");
    if (submitBtn instanceof HTMLButtonElement) {
      submitBtn.disabled = true;
      submitBtn.classList.add("is-loading");
    }

    try {
      const fd = new FormData(form);
      const res = await fetch(`${form.action}?format=json`, {
        method: "POST",
        body: fd,
        headers: { Accept: "application/json" },
      });
      const data = await readJson(res);
      if (!res.ok || !data.ok) {
        throw new Error(data.error || "Could not create post.");
      }

      form.reset();
      const textarea = form.querySelector("textarea[name='body']");
      if (textarea instanceof HTMLTextAreaElement) {
        textarea.style.height = "auto";
      }

      const postUrl = data?.post?.url || "/";
      setFlash(
        "success",
        `Posted successfully. <a href="${postUrl}" data-no-fx="1">Open your post</a>`
      );
    } catch (err) {
      setFlash("error", (err instanceof Error ? err.message : "Could not create post."));
    } finally {
      if (submitBtn instanceof HTMLButtonElement) {
        submitBtn.disabled = false;
        submitBtn.classList.remove("is-loading");
      }
    }
  });
})();
