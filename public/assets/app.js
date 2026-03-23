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
  let stack = null;

  const ensureStack = () => {
    if (stack instanceof HTMLElement) return stack;
    stack = document.createElement("div");
    stack.className = "toastStack";
    stack.setAttribute("aria-live", "polite");
    stack.setAttribute("aria-atomic", "true");
    document.body.appendChild(stack);
    return stack;
  };

  window.truxToast = (message, type = "success") => {
    if (!message) return;

    const host = ensureStack();
    const toast = document.createElement("div");
    toast.className = `toast toast--${type}`;
    toast.textContent = String(message);
    host.appendChild(toast);

    window.requestAnimationFrame(() => {
      toast.classList.add("is-visible");
    });

    window.setTimeout(() => {
      toast.classList.remove("is-visible");
      window.setTimeout(() => {
        toast.remove();
      }, 220);
    }, 2200);
  };
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
          `${(window.TRUX_BASE_URL || "")}/mention_suggestions.php?q=${encodeURIComponent(context.query)}`,
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
  const editModal = document.getElementById("entityEditModal");
  const editForm = editModal?.querySelector("[data-entity-edit-form='1']");
  const editTitle = editModal?.querySelector("[data-edit-title='1']");
  const editLabel = editModal?.querySelector("[data-edit-label='1']");
  const editTypeField = editModal?.querySelector("[data-edit-type='1']");
  const editIdField = editModal?.querySelector("[data-edit-id='1']");
  const editFlash = editModal?.querySelector("[data-edit-flash='1']");
  const editTextarea = editForm?.querySelector("textarea[name='body']");
  const editSubmitBtn = editForm?.querySelector("[data-edit-submit='1']");
  const postPagePath = new URL(`${window.TRUX_BASE_URL || ""}/post.php`, window.location.origin).pathname;
  const isStandalonePostRoute = window.location.pathname === postPagePath;

  let currentPostId = 0;
  let lastTrigger = null;
  let renderedComments = [];
  let editReturnFocus = null;

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

  const hasEditUi =
    editModal instanceof HTMLElement &&
    editForm instanceof HTMLFormElement &&
    editTitle instanceof HTMLElement &&
    editLabel instanceof HTMLElement &&
    editTypeField instanceof HTMLInputElement &&
    editIdField instanceof HTMLInputElement &&
    editFlash instanceof HTMLElement &&
    editTextarea instanceof HTMLTextAreaElement &&
    editSubmitBtn instanceof HTMLButtonElement;

  const setEditFlash = (message = "") => {
    if (!(editFlash instanceof HTMLElement)) return;
    const text = String(message || "").trim();
    if (!text) {
      editFlash.hidden = true;
      editFlash.textContent = "";
      return;
    }
    editFlash.hidden = false;
    editFlash.textContent = text;
  };

  const isEditOpen = () =>
    editModal instanceof HTMLElement && !editModal.hasAttribute("hidden");

  const setEditBusy = (busy) => {
    if (editSubmitBtn instanceof HTMLButtonElement) {
      editSubmitBtn.disabled = !!busy;
    }
    if (editTextarea instanceof HTMLTextAreaElement) {
      editTextarea.readOnly = !!busy;
    }
  };

  const openEditModal = (options = {}) => {
    if (!hasEditUi || !(editModal instanceof HTMLElement) || !(editTextarea instanceof HTMLTextAreaElement)) {
      return false;
    }

    const entityType = String(options.entityType || "");
    const entityId = Number(options.entityId || 0);
    const currentBody = String(options.currentBody || "");
    const trigger = options.trigger;
    if (!entityType || entityId <= 0) {
      return false;
    }

    const isPost = entityType === "post";
    const titleText = isPost ? "Edit post" : "Edit comment / reply";
    const labelText = isPost ? "Update your post" : "Update your comment / reply";
    const placeholderText = isPost ? "Edit your post..." : "Edit your comment...";
    const maxLength = isPost ? 2000 : 1000;

    editReturnFocus =
      trigger instanceof HTMLElement || trigger instanceof HTMLButtonElement ? trigger : null;
    editTypeField.value = entityType;
    editIdField.value = String(entityId);
    if (editTitle instanceof HTMLElement) editTitle.textContent = titleText;
    if (editLabel instanceof HTMLElement) editLabel.textContent = labelText;
    editTextarea.maxLength = maxLength;
    editTextarea.placeholder = placeholderText;
    editTextarea.value = currentBody;
    editTextarea.style.height = "auto";
    editTextarea.style.height = Math.min(editTextarea.scrollHeight, 420) + "px";
    setEditFlash("");
    setEditBusy(false);

    editModal.removeAttribute("hidden");
    document.body.classList.add("entityEdit-open");
    window.setTimeout(() => {
      editTextarea.focus();
      const len = editTextarea.value.length;
      editTextarea.setSelectionRange(len, len);
    }, 20);

    return true;
  };

  const closeEditModal = (options = {}) => {
    if (!(editModal instanceof HTMLElement)) return;
    if (!isEditOpen()) return;

    const shouldRestoreFocus = options.restoreFocus !== false;
    editModal.setAttribute("hidden", "hidden");
    document.body.classList.remove("entityEdit-open");
    setEditFlash("");
    setEditBusy(false);
    if (editTypeField instanceof HTMLInputElement) editTypeField.value = "";
    if (editIdField instanceof HTMLInputElement) editIdField.value = "";

    if (shouldRestoreFocus && editReturnFocus && typeof editReturnFocus.focus === "function") {
      editReturnFocus.focus();
    }
    editReturnFocus = null;
  };

  const ownerMenuMarkup = (entityType, entityId, bookmarked = false) => `
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
          class="contentMenu__item${bookmarked ? " is-active" : ""}"
          type="button"
          role="menuitem"
          data-owner-bookmark="1"
          data-owner-type="${esc(entityType)}"
          aria-label="${bookmarked ? "Remove bookmark" : "Bookmark"}"
          data-owner-id="${entityId}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m7 5 5-2 5 2v14l-5-2-5 2V5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          </svg>
          <span data-owner-bookmark-label="1">${bookmarked ? "Saved" : "Bookmark"}</span>
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

  const syncPostBookmarkState = (postId, bookmarked) => {
    document
      .querySelectorAll(`form[data-ajax-action="1"][data-action-kind="bookmark"][data-post-id="${postId}"] .postAct`)
      .forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        node.classList.toggle("is-active", !!bookmarked);
        node.setAttribute("aria-label", bookmarked ? "Remove bookmark" : "Bookmark post");
        const label = node.querySelector("[data-action-label='bookmark']");
        if (label instanceof HTMLElement) {
          label.textContent = bookmarked ? "Saved" : "Bookmark";
        }
      });

    document
      .querySelectorAll(`[data-owner-bookmark="1"][data-owner-type="post"][data-owner-id="${postId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        node.classList.toggle("is-active", !!bookmarked);
        node.setAttribute("aria-label", bookmarked ? "Remove bookmark" : "Bookmark");
        const label = node.querySelector("[data-owner-bookmark-label='1']");
        if (label instanceof HTMLElement) {
          label.textContent = bookmarked ? "Saved" : "Bookmark";
        }
      });
  };

  const syncPostBookmarkCount = (postId, count) => {
    document.querySelectorAll(`[data-bookmark-count-for="${postId}"]`).forEach((node) => {
      node.textContent = String(count);
    });
  };

  const notifyMenuAction = (message) => {
    if (!message) return;
    if (typeof window.truxToast === "function") {
      window.truxToast(message);
      return;
    }
    window.alert(message);
  };

  const resolveAbsoluteUrl = (value) => {
    try {
      return new URL(String(value || "/"), window.location.origin).toString();
    } catch {
      return window.location.href;
    }
  };

  const copyText = async (value) => {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
      await navigator.clipboard.writeText(value);
      return;
    }

    const helper = document.createElement("textarea");
    helper.value = value;
    helper.setAttribute("readonly", "readonly");
    helper.style.position = "fixed";
    helper.style.opacity = "0";
    helper.style.pointerEvents = "none";
    document.body.appendChild(helper);
    helper.select();
    helper.setSelectionRange(0, helper.value.length);

    let copied = false;
    try {
      copied = document.execCommand("copy");
    } finally {
      helper.remove();
    }

    if (!copied) {
      throw new Error("Copy unavailable.");
    }
  };

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
      flash.innerHTML = `Post deleted. <a href="${window.TRUX_BASE_URL || ""}/" data-no-fx="1">Back to feed</a>`;
      parent.insertBefore(flash, singlePost);
    }

    posts.forEach((node) => node.remove());
  };

  const isOpen = () => !dock.hasAttribute("hidden");
  const getStandalonePostExitUrl = () => {
    const fallback = `${window.TRUX_BASE_URL || ""}/`;
    const referrer = String(document.referrer || "").trim();
    if (!referrer) {
      return fallback;
    }

    try {
      const referrerUrl = new URL(referrer, window.location.origin);
      if (referrerUrl.origin !== window.location.origin) {
        return fallback;
      }
      if (referrerUrl.pathname === window.location.pathname && referrerUrl.search === window.location.search) {
        return fallback;
      }
      return referrerUrl.toString();
    } catch {
      return fallback;
    }
  };

  const setCommentCount = (postId, count) => {
    const nodes = document.querySelectorAll(`[data-comment-count-for="${postId}"]`);
    nodes.forEach((n) => {
      n.textContent = String(count);
    });
  };

  const resetCommentPaging = () => {
    renderedComments = [];
  };

  const mergeUniqueComments = (existing, incoming) => {
    const byId = new Map();
    if (Array.isArray(existing)) {
      existing.forEach((item) => {
        const id = Number(item?.id || 0);
        if (id > 0) byId.set(id, item);
      });
    }

    if (Array.isArray(incoming)) {
      incoming.forEach((item) => {
        const id = Number(item?.id || 0);
        if (id > 0) byId.set(id, item);
      });
    }

    return Array.from(byId.values()).sort(
      (a, b) => Number(a?.id || 0) - Number(b?.id || 0)
    );
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

  const setCommentBookmarkState = (commentId, bookmarked) => {
    document
      .querySelectorAll(`[data-comment-bookmark="1"][data-comment-id="${commentId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        node.classList.toggle("is-active", !!bookmarked);
        node.setAttribute("aria-label", bookmarked ? "Remove bookmark" : "Bookmark comment");
        const label = node.querySelector("[data-comment-bookmark-label='1']");
        if (label instanceof HTMLElement) {
          label.textContent = bookmarked ? "Saved" : "Bookmark";
        }
      });

    document
      .querySelectorAll(`[data-owner-bookmark="1"][data-owner-type="comment"][data-owner-id="${commentId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        node.classList.toggle("is-active", !!bookmarked);
        node.setAttribute("aria-label", bookmarked ? "Remove bookmark" : "Bookmark");
        const label = node.querySelector("[data-owner-bookmark-label='1']");
        if (label instanceof HTMLElement) {
          label.textContent = bookmarked ? "Saved" : "Bookmark";
        }
      });
  };

  const highlightComment = (commentId) => {
    const target = document.querySelector(`.commentDock__item[data-comment-id="${commentId}"]`);
    if (!(target instanceof HTMLElement)) return;

    target.classList.add("is-highlighted");
    target.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(() => {
      target.classList.remove("is-highlighted");
    }, 2400);
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
    const avatarPath = typeof c.avatar_path === "string" ? c.avatar_path.trim() : "";
    const avatarMarkup =
      avatarPath !== ""
        ? `<img class="commentDock__avatarImage" src="${esc(avatarPath)}" alt="" loading="lazy" decoding="async">`
        : `
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 12a4.2 4.2 0 1 0-4.2-4.2A4.2 4.2 0 0 0 12 12Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" fill="currentColor" />
            </svg>
          `;
    const commentBodyHtml =
      typeof c.body_html === "string" && c.body_html !== ""
        ? c.body_html
        : bodyToHtml(c.body || "");
    item.innerHTML = `
      <div class="commentDock__meta">
        <div class="commentDock__author">
          <a class="commentDock__avatar${avatarPath !== "" ? " commentDock__avatar--image" : ""}" href="${(window.TRUX_BASE_URL || "")}/profile.php?u=${encodeURIComponent(c.username)}" aria-label="View @${esc(c.username)} profile">
            ${avatarMarkup}
          </a>
          <a class="commentDock__user" href="${(window.TRUX_BASE_URL || "")}/profile.php?u=${encodeURIComponent(c.username)}">@${esc(c.username)}</a>
        </div>
        <div class="commentDock__metaEnd">
          <span
            class="commentDock__time"
            data-time-ago="1"
            data-time-source="${esc(c.created_at)}"
            title="${esc(c.exact_time)}">${esc(c.time_ago)}</span>
          ${editedMetaMarkup(c.edited_at, c.edited_time_ago, c.edited_exact_time)}
          ${c.is_owner ? ownerMenuMarkup("comment", c.id, !!c.bookmarked) : ""}
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
        <div class="commentDock__actionButtons">
          <button
            type="button"
            class="commentDock__bookmarkBtn${c.bookmarked ? " is-active" : ""}"
            data-comment-bookmark="1"
            data-comment-id="${c.id}"
            aria-label="${c.bookmarked ? "Remove bookmark" : "Bookmark comment"}">
            <span data-comment-bookmark-label="1">${c.bookmarked ? "Saved" : "Bookmark"}</span>
          </button>
          ${
            c.can_report
              ? `<button
            type="button"
            class="commentDock__reportBtn"
            data-report-action="1"
            data-report-target-type="comment"
            data-report-target-id="${c.id}"
            data-report-open-url="${esc(c.report_url || `${window.TRUX_BASE_URL || ""}/post.php?id=${c.post_id}&viewer=1&comment_id=${c.id}`)}"
            data-report-target-label="${esc(c.report_label || `Comment #${c.id} by @${c.username}`)}">Report</button>`
              : ""
          }
          <button
            type="button"
            class="commentDock__replyBtn"
            data-comment-reply="1"
            data-comment-id="${c.id}"
            data-comment-user-id="${c.user_id}"
            data-comment-username="${esc(c.username)}">Reply</button>
        </div>
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

  const loadComments = async (postId, options = {}) => {
    if (!list) return [];

    const focusCommentId = Number(options.focusCommentId || 0);
    const scrollToEnd = !!options.scrollToEnd;
    list.innerHTML = `<div class="muted">Loading comments...</div>`;
    if (empty) empty.hidden = true;
    resetCommentPaging();

    try {
      let nextComments = [];
      let beforeCursor = null;
      let hasMore = true;
      let totalCount = 0;
      let guard = 0;

      while (hasMore && guard < 40) {
        const params = new URLSearchParams({
          id: String(postId),
          limit: "200",
        });
        if (beforeCursor !== null && beforeCursor > 0) {
          params.set("before", String(beforeCursor));
        }

        const res = await fetch(`${window.TRUX_BASE_URL || ""}/post_comments.php?${params.toString()}`, {
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || "Could not load comments.");

        const incoming = Array.isArray(data.comments) ? data.comments : [];
        nextComments = mergeUniqueComments(nextComments, incoming);
        totalCount = Number(data.total_count || data.count || nextComments.length || 0);

        const nextBefore = Number(data.next_before || 0);
        hasMore = !!data.has_more && nextBefore > 0;
        beforeCursor = hasMore ? nextBefore : null;
        guard += 1;
      }

      renderComments(nextComments);
      setCommentCount(postId, totalCount);

      if (focusCommentId > 0) {
        window.requestAnimationFrame(() => highlightComment(focusCommentId));
      } else if (listWrap instanceof HTMLElement) {
        listWrap.scrollTop = scrollToEnd ? listWrap.scrollHeight : 0;
      }

      return nextComments;
    } catch (err) {
      list.innerHTML = `<div class="flash flash--error">Could not load comments.</div>`;
      if (empty) empty.hidden = true;
      console.error(err);
      return renderedComments;
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
    clone.removeAttribute("data-post-click-target");
    clone.removeAttribute("data-post-url");

    clone.querySelectorAll("[data-post-open-viewer-link='1']").forEach((el) => el.remove());
    clone.querySelectorAll(".row.row--spaced").forEach((el) => el.remove());
    clone.querySelectorAll("[data-comment-open]").forEach((el) => {
      el.setAttribute("disabled", "disabled");
      el.setAttribute("aria-disabled", "true");
    });

    postPane.appendChild(clone);
  };

  const openDock = (trigger, options = {}) => {
    const postId = Number(trigger.getAttribute("data-post-id") || "0");
    const focusCommentId = Number(options.commentId || "0");
    if (!postId) return;

    currentPostId = postId;
    lastTrigger = trigger;

    if (postIdField) postIdField.value = String(postId);

    fillPostPane(trigger, postId);
    dock.removeAttribute("hidden");
    document.body.classList.add("commentDock-open");
    clearReplyState();
    if (listWrap instanceof HTMLElement) {
      listWrap.scrollTop = 0;
    }
    void loadComments(postId, { focusCommentId }).then((comments) => {
      renderedComments = comments;
    });

    const input = form ? form.querySelector("textarea[name='body']") : null;
    if (input instanceof HTMLTextAreaElement) {
      input.value = "";
      window.setTimeout(() => input.focus(), 30);
    }
  };

  const closeDock = (options = {}) => {
    if (!isOpen()) return;
    closeEditModal({ restoreFocus: false });
    dock.setAttribute("hidden", "hidden");
    document.body.classList.remove("commentDock-open");
    currentPostId = 0;
    if (postIdField) postIdField.value = "";
    clearReplyState();
    resetCommentPaging();
    if (options.exitStandaloneRoute && isStandalonePostRoute) {
      window.location.assign(getStandalonePostExitUrl());
      return;
    }
    if (lastTrigger && typeof lastTrigger.focus === "function") {
      lastTrigger.focus();
    }
  };

  document.addEventListener("click", async (e) => {
    if (!(e.target instanceof Element)) return;

    const editCloseTrigger = e.target.closest("[data-edit-close='1'], [data-edit-cancel='1']");
    if (editCloseTrigger instanceof HTMLElement) {
      e.preventDefault();
      closeEditModal();
      return;
    }

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
      if (!entityType || !entityId) {
        window.alert("Action unavailable right now.");
        return;
      }

      const isPost = entityType === "post";
      const sourceNode = isPost
        ? document.querySelector(`.post[data-post-id="${entityId}"] .post__body`)
        : document.querySelector(`[data-comment-id="${entityId}"] .commentDock__body`);
      const currentBody = sourceNode instanceof HTMLElement ? sourceNode.innerText.trim() : "";

      if (!openEditModal({ entityType, entityId, currentBody, trigger: ownerEditBtn })) {
        window.alert("Editor unavailable right now.");
      }
      return;
    }

    const ownerBookmarkBtn = e.target.closest("[data-owner-bookmark='1']");
    if (ownerBookmarkBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      closeAllContentMenus();

      const entityType = ownerBookmarkBtn.getAttribute("data-owner-type") || "";
      const entityId = Number(ownerBookmarkBtn.getAttribute("data-owner-id") || "0");
      const csrf = getCsrfToken();
      if (!entityType || !entityId || !csrf) {
        window.alert("Action unavailable right now.");
        return;
      }

      const endpoint =
        entityType === "post" ? `${(window.TRUX_BASE_URL || "")}/bookmark_post.php?format=json` : `${(window.TRUX_BASE_URL || "")}/bookmark_comment.php?format=json`;

      ownerBookmarkBtn.disabled = true;
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
          throw new Error(data.error || "Could not update bookmark.");
        }

        if (entityType === "post") {
          syncPostBookmarkState(entityId, !!data.bookmarked);
          if (typeof data.bookmarks_count === "number") {
            syncPostBookmarkCount(entityId, data.bookmarks_count);
          }
          if (!data.bookmarked && window.location.href.includes("/bookmarks.php")) {
            removePostCards(entityId);
          }
        } else {
          setCommentBookmarkState(entityId, !!data.bookmarked);
        }
        if (typeof window.truxToast === "function") {
          window.truxToast(data.bookmarked ? "Saved to bookmarks" : "Removed from bookmarks");
        }
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not update bookmark.");
      } finally {
        ownerBookmarkBtn.disabled = false;
      }
      return;
    }

    const copyLinkBtn = e.target.closest("[data-post-copy-link='1']");
    if (copyLinkBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      closeAllContentMenus();

      const postUrl = resolveAbsoluteUrl(copyLinkBtn.getAttribute("data-post-url") || `${window.TRUX_BASE_URL || ""}/`);
      try {
        await copyText(postUrl);
        notifyMenuAction("Post link copied");
      } catch {
        window.alert(`Copy this post link:\n\n${postUrl}`);
      }
      return;
    }

    const placeholderActionBtn = e.target.closest("[data-post-placeholder-action='1']");
    if (placeholderActionBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      closeAllContentMenus();

      const fallbackLabel = placeholderActionBtn.getAttribute("data-post-placeholder-label") || "This action";
      const message =
        placeholderActionBtn.getAttribute("data-post-placeholder-message") || `${fallbackLabel} is coming soon.`;
      notifyMenuAction(message);
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

      const endpoint = isPost ? `${(window.TRUX_BASE_URL || "")}/delete_post.php?format=json` : `${(window.TRUX_BASE_URL || "")}/delete_comment.php?format=json`;
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
          renderedComments = await loadComments(Number(data.post_id || currentPostId || 0));
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

    const postCard = e.target.closest("[data-post-click-target='1']");
    if (postCard instanceof HTMLElement) {
      const ignoreSelector = [
        "a",
        "button",
        "form",
        "input",
        "label",
        "select",
        "textarea",
        "[data-content-menu='1']",
        ".post__actionsBar",
        ".post__actions",
      ].join(", ");

      if (e.target.closest(ignoreSelector)) {
        return;
      }

      const selection = window.getSelection ? window.getSelection() : null;
      if (selection && String(selection).trim() !== "") {
        return;
      }

      e.preventDefault();
      openDock(postCard);
      return;
    }

    const closeBtn = e.target.closest("[data-comment-close]");
    if (closeBtn instanceof HTMLElement) {
      e.preventDefault();
      closeDock({ exitStandaloneRoute: true });
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

    const bookmarkBtn = e.target.closest("[data-comment-bookmark='1']");
    if (bookmarkBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      const commentId = Number(bookmarkBtn.getAttribute("data-comment-id") || "0");
      if (!commentId) return;

      const csrf = getCsrfToken();
      if (!csrf) {
        window.location.href = (window.TRUX_BASE_URL || "") + "/login.php";
        return;
      }

      const relatedButtons = document.querySelectorAll(`[data-comment-bookmark="1"][data-comment-id="${commentId}"]`);
      relatedButtons.forEach((node) => {
        if (node instanceof HTMLButtonElement) node.disabled = true;
      });

      try {
        const res = await fetch(`${window.TRUX_BASE_URL || ""}/bookmark_comment.php?format=json`, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: new URLSearchParams({
            _csrf: csrf,
            id: String(commentId),
          }),
        });
        const data = await readJson(res);
        if (res.status === 401 && data.login_url) {
          window.location.href = String(data.login_url);
          return;
        }
        if (!res.ok || !data.ok) {
          throw new Error(data.error || "Could not update bookmark.");
        }

        setCommentBookmarkState(commentId, !!data.bookmarked);
        if (typeof window.truxToast === "function") {
          window.truxToast(data.bookmarked ? "Saved to bookmarks" : "Removed from bookmarks");
        }
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not update bookmark.");
      } finally {
        relatedButtons.forEach((node) => {
          if (node instanceof HTMLButtonElement) node.disabled = false;
        });
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
        window.location.href = (window.TRUX_BASE_URL || "") + "/login.php";
        return;
      }

      const relatedButtons = document.querySelectorAll(`[data-comment-vote="1"][data-comment-id="${commentId}"]`);
      relatedButtons.forEach((node) => {
        if (node instanceof HTMLButtonElement) node.disabled = true;
      });

      try {
        const res = await fetch(`${window.TRUX_BASE_URL || ""}/vote_comment.php?format=json`, {
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
      const reportModal = document.getElementById("postReportModal");
      if (
        reportModal instanceof HTMLElement &&
        !reportModal.hasAttribute("hidden")
      ) {
        e.preventDefault();
        const closeTrigger = reportModal.querySelector(
          "[data-report-close='1'], [data-report-cancel='1']"
        );
        if (closeTrigger instanceof HTMLElement) {
          closeTrigger.click();
        }
        return;
      }
      if (isEditOpen()) {
        e.preventDefault();
        closeEditModal();
        return;
      }
      closeAllContentMenus();
      closeDock({ exitStandaloneRoute: true });
    }
  });

  if (
    editForm instanceof HTMLFormElement &&
    editTypeField instanceof HTMLInputElement &&
    editIdField instanceof HTMLInputElement &&
    editTextarea instanceof HTMLTextAreaElement
  ) {
    editForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      const entityType = editTypeField.value || "";
      const entityId = Number(editIdField.value || "0");
      const isPost = entityType === "post";
      if (!entityType || entityId <= 0) {
        setEditFlash("Action unavailable right now.");
        return;
      }

      const csrf = getCsrfToken();
      if (!csrf) {
        setEditFlash("Session expired. Please refresh the page and try again.");
        return;
      }

      const trimmedBody = editTextarea.value.trim();
      if (!trimmedBody) {
        setEditFlash(isPost ? "Post cannot be empty." : "Comment cannot be empty.");
        return;
      }

      const endpoint = isPost
        ? `${window.TRUX_BASE_URL || ""}/edit_post.php?format=json`
        : `${window.TRUX_BASE_URL || ""}/edit_comment.php?format=json`;

      setEditFlash("");
      setEditBusy(true);
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
          const targetPostId = Number(data.post_id || currentPostId || 0);
          if (targetPostId > 0) {
            renderedComments = await loadComments(targetPostId);
          }
        }

        closeEditModal();
        if (typeof window.truxToast === "function") {
          window.truxToast("Changes saved");
        }
      } catch (err) {
        setEditFlash(err instanceof Error ? err.message : "Could not save changes.");
      } finally {
        setEditBusy(false);
      }
    });
  }

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

        const res = await fetch(`${window.TRUX_BASE_URL || ""}/comment_post.php?format=json`, {
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
        renderedComments = await loadComments(currentPostId, { scrollToEnd: true });
      } catch (err) {
        window.alert(err instanceof Error ? err.message : "Could not add comment.");
      } finally {
        if (submitBtn instanceof HTMLButtonElement) submitBtn.disabled = false;
      }
    });
  }

  const params = new URLSearchParams(window.location.search);
  const autoCommentId = Number(params.get("comment_id") || "0");
  const autoPostId = Number(params.get("id") || "0");
  const viewerMode = params.get("viewer") === "1";
  if (autoPostId > 0 && (autoCommentId > 0 || viewerMode || isStandalonePostRoute)) {
    const autoTrigger =
      document.querySelector(`[data-comment-open="1"][data-post-id="${autoPostId}"]`) ||
      document.querySelector(`.post[data-post-id="${autoPostId}"]`);
    if (autoTrigger instanceof HTMLElement) {
      window.setTimeout(() => openDock(autoTrigger, { commentId: autoCommentId }), 80);
    }
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
      bookmark: `[data-bookmark-count-for="${postId}"]`,
    };
    const selector = selectorByKind[kind];
    if (!selector) return;
    document.querySelectorAll(selector).forEach((el) => {
      el.textContent = String(count);
    });
  };

  const setOwnerBookmarkState = (postId, bookmarked) => {
    document
      .querySelectorAll(`[data-owner-bookmark="1"][data-owner-type="post"][data-owner-id="${postId}"]`)
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        node.classList.toggle("is-active", !!bookmarked);
        node.setAttribute("aria-label", bookmarked ? "Remove bookmark" : "Bookmark");
        const label = node.querySelector("[data-owner-bookmark-label='1']");
        if (label instanceof HTMLElement) {
          label.textContent = bookmarked ? "Saved" : "Bookmark";
        }
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
        } else if (kind === "bookmark") {
          btn.setAttribute("aria-label", active ? "Remove bookmark" : "Bookmark post");
          const label = btn.querySelector("[data-action-label='bookmark']");
          if (label instanceof HTMLElement) {
            label.textContent = active ? "Saved" : "Bookmark";
          }
          setOwnerBookmarkState(postId, active);
        }
      });
  };

  const setActionLoading = (form, loading) => {
    const btn = form.querySelector("button[type='submit']");
    if (!(btn instanceof HTMLButtonElement)) return;
    btn.disabled = loading;
    btn.classList.toggle("is-loading", loading);
  };

  const syncBookmarkOwnerStates = (root = document) => {
    if (!root || typeof root.querySelectorAll !== "function") return;

    root.querySelectorAll('form[data-ajax-action="1"][data-action-kind="bookmark"]').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      const postId = Number(form.dataset.postId || "0");
      const button = form.querySelector(".postAct");
      if (!postId || !(button instanceof HTMLElement)) return;
      setOwnerBookmarkState(postId, button.classList.contains("is-active"));
    });
  };

  syncBookmarkOwnerStates(document);

  document.addEventListener("trux:content-added", (e) => {
    const root = e instanceof CustomEvent ? e.detail?.root : null;
    syncBookmarkOwnerStates(root && typeof root.querySelectorAll === "function" ? root : document);
  });

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
      } else if (kind === "bookmark") {
        if (typeof data.bookmarks_count === "number") {
          setActionCount("bookmark", postId, data.bookmarks_count);
        }
        setActionActive("bookmark", postId, !!data.bookmarked);
        if (!data.bookmarked && window.location.href.includes("/bookmarks.php")) {
          removePostCards(postId);
        }
        if (typeof window.truxToast === "function") {
          window.truxToast(data.bookmarked ? "Saved to bookmarks" : "Removed from bookmarks");
        }
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

      const postUrl = new URL(
        String(data?.post?.url || `${window.TRUX_BASE_URL || ""}/`),
        window.location.origin
      ).toString();
      window.location.assign(postUrl);
      return;
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

(() => {
  if (!window.location.href.includes("/messages.php")) return;

  const layout = document.querySelector("[data-messages-active-conversation-id]");
  if (!(layout instanceof HTMLElement)) return;

  const conversationId = Number(
    layout.getAttribute("data-messages-active-conversation-id") || "0"
  );
  if (!conversationId) return;

  const csrfInput = document.querySelector("input[name='_csrf']");
  if (!(csrfInput instanceof HTMLInputElement) || !csrfInput.value) return;

  const payload = new URLSearchParams({
    _csrf: csrfInput.value,
    id: String(conversationId),
  });

  fetch(`${window.TRUX_BASE_URL || ""}/mark_conversation_read.php?format=json`, {
    method: "POST",
    headers: { Accept: "application/json" },
    body: payload,
  }).catch(() => {
    // Ignore failures; message rendering should continue.
  });
})();

(() => {
  const rows = document.querySelectorAll("[data-link-preview-row='1']");
  if (!rows.length) return;

  const providerNames = {
    website: "Website",
    x: "X / Twitter",
    reddit: "Reddit",
    instagram: "Instagram",
    facebook: "Facebook",
    linkedin: "LinkedIn",
    github: "GitHub",
    youtube: "YouTube",
    tiktok: "TikTok",
    twitch: "Twitch",
    discord: "Discord",
  };

  const icons = {
    website:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm6.8 8h-3.1a14.8 14.8 0 0 0-1.3-5.1A7.1 7.1 0 0 1 18.8 11ZM12 4.9c1 1.2 1.8 3.4 2 6.1H10c.2-2.7 1-4.9 2-6.1ZM9.6 5.9A14.8 14.8 0 0 0 8.3 11H5.2a7.1 7.1 0 0 1 4.4-5.1ZM5.2 13h3.1a14.8 14.8 0 0 0 1.3 5.1A7.1 7.1 0 0 1 5.2 13Zm6.8 6.1c-1-1.2-1.8-3.4-2-6.1h4c-.2 2.7-1 4.9-2 6.1Zm2.4-1a14.8 14.8 0 0 0 1.3-5.1h3.1a7.1 7.1 0 0 1-4.4 5.1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    x: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 4h3.7l3 4.3L16.8 4H19l-4.9 5.9L19.6 20h-3.7l-3.3-4.8L8.4 20H6.2l5.3-6.4L6.4 4Z"/></svg>',
    reddit:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.4 9.1a1.8 1.8 0 1 0-1.8-3 5.8 5.8 0 0 0-3.5-1l.5-2.2 1.6.4a1.7 1.7 0 1 0 .4-1.4l-2-.5a.8.8 0 0 0-1 .6l-.6 2.8a6.8 6.8 0 0 0-4 1.2 1.8 1.8 0 1 0-1.6 3c-.1.4-.2.9-.2 1.4 0 3.1 2.6 5.7 5.8 5.7 3.2 0 5.8-2.6 5.8-5.7 0-.5-.1-.9-.2-1.3Zm-9.8 3a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Zm6.8 1.4c-.8.8-2 1.2-3.4 1.2s-2.6-.4-3.4-1.2a.8.8 0 0 1 1.2-1 3.6 3.6 0 0 0 2.2.7c.9 0 1.7-.2 2.2-.7a.8.8 0 0 1 1.2 1Zm-.1-1.4a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Z"/></svg>',
    instagram:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2.1A2.9 2.9 0 0 0 4.1 7v10A2.9 2.9 0 0 0 7 19.9h10a2.9 2.9 0 0 0 2.9-2.9V7A2.9 2.9 0 0 0 17 4.1H7Zm10.4 1.5a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM12 6.3A5.7 5.7 0 1 1 6.3 12 5.7 5.7 0 0 1 12 6.3Zm0 2.1A3.6 3.6 0 1 0 15.6 12 3.6 3.6 0 0 0 12 8.4Z"/></svg>',
    facebook:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.8l.5-3.5h-3.3V8.3c0-1 .3-1.8 1.8-1.8H17V3.4c-.3 0-1.3-.2-2.5-.2-2.8 0-4.5 1.7-4.5 4.9v2.4H7v3.5h3V21h3.5Z"/></svg>',
    linkedin:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5.2 8.8A1.9 1.9 0 1 1 5.2 5a1.9 1.9 0 0 1 0 3.8ZM3.6 10h3.2v10H3.6V10Zm5.3 0H12v1.4h.1c.4-.8 1.5-1.8 3.1-1.8 3.3 0 3.9 2.2 3.9 5V20h-3.2v-4.6c0-1.1 0-2.5-1.6-2.5s-1.8 1.2-1.8 2.4V20H8.9V10Z"/></svg>',
    github:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.2A10 10 0 0 0 8.8 21c.5.1.7-.2.7-.5v-1.7c-3 .7-3.7-1.3-3.7-1.3-.5-1.2-1.1-1.5-1.1-1.5-.9-.6 0-.6 0-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .8.1-.7.4-1.1.7-1.4-2.4-.3-5-1.2-5-5.3 0-1.1.4-2 1-2.8 0-.2-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.7 9.7 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.1 2.5 0 2.7.6.8 1 1.7 1 2.8 0 4.1-2.5 5-5 5.3.4.3.8 1 .8 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2.2Z"/></svg>',
    youtube:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.5 7.2a2.9 2.9 0 0 0-2-2c-1.8-.5-7.5-.5-7.5-.5s-5.7 0-7.5.5a2.9 2.9 0 0 0-2 2A30.5 30.5 0 0 0 2 12a30.5 30.5 0 0 0 .5 4.8 2.9 2.9 0 0 0 2 2c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a2.9 2.9 0 0 0 2-2A30.5 30.5 0 0 0 22 12a30.5 30.5 0 0 0-.5-4.8ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>',
    tiktok:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 3h2.2c.2 1.3 1.1 2.5 2.3 3.1.6.3 1.2.5 1.9.5V9a7.3 7.3 0 0 1-4.2-1.4v6.5a5.1 5.1 0 1 1-5.1-5v2.4a2.7 2.7 0 1 0 2.7 2.7V3Z"/></svg>',
    twitch:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 3h17v11.3l-3.4 3.4h-3l-2.5 2.5H9.7v-2.5H4V3Zm1.9 1.9v10.9h4.4v2.1l2.1-2.1h3.5l2.2-2.2V4.9H5.9Zm5.4 2.7h1.9v5h-1.9v-5Zm4.4 0h1.9v5h-1.9v-5Z"/></svg>',
    discord:
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.7 5.7a14 14 0 0 0-3.5-1.1l-.2.4a10 10 0 0 1 2.9 1.1A13 13 0 0 0 12 4.6 13 13 0 0 0 5.1 6a10 10 0 0 1 2.9-1.1l-.2-.4c-1.2.2-2.4.6-3.5 1.1C2 9 1.4 12.2 1.6 15.3c1.5 1.1 3 1.8 4.5 2.3l1.1-1.8a9 9 0 0 1-1.8-.9l.4-.3c3.4 1.6 7.1 1.6 10.4 0l.4.3c-.6.4-1.2.7-1.8.9l1.1 1.8c1.5-.5 3-1.2 4.5-2.3.3-3.6-.6-6.8-2.7-9.6ZM9.5 13.3c-.8 0-1.4-.8-1.4-1.7 0-1 .6-1.7 1.4-1.7.8 0 1.4.8 1.4 1.7 0 1-.6 1.7-1.4 1.7Zm5 0c-.8 0-1.4-.8-1.4-1.7 0-1 .6-1.7 1.4-1.7.8 0 1.4.8 1.4 1.7 0 1-.6 1.7-1.4 1.7Z"/></svg>',
  };

  const normalizeCandidateUrl = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    return /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
  };

  const providerFromUrl = (value) => {
    const normalized = normalizeCandidateUrl(value);
    if (!normalized) return "website";

    try {
      const url = new URL(normalized);
      let host = String(url.hostname || "").toLowerCase();
      if (host.startsWith("www.")) {
        host = host.slice(4);
      }

      if (host === "x.com" || host === "twitter.com" || host.endsWith(".x.com") || host.endsWith(".twitter.com")) return "x";
      if (host === "reddit.com" || host.endsWith(".reddit.com")) return "reddit";
      if (host === "instagram.com" || host.endsWith(".instagram.com")) return "instagram";
      if (host === "facebook.com" || host === "fb.com" || host.endsWith(".facebook.com")) return "facebook";
      if (host === "linkedin.com" || host.endsWith(".linkedin.com")) return "linkedin";
      if (host === "github.com" || host.endsWith(".github.com")) return "github";
      if (host === "youtube.com" || host === "youtu.be" || host.endsWith(".youtube.com")) return "youtube";
      if (host === "tiktok.com" || host.endsWith(".tiktok.com")) return "tiktok";
      if (host === "twitch.tv" || host.endsWith(".twitch.tv")) return "twitch";
      if (host === "discord.com" || host === "discord.gg" || host.endsWith(".discord.com")) return "discord";
    } catch {
      return "website";
    }

    return "website";
  };

  const labelFromInputs = (labelValue, urlValue) => {
    const label = String(labelValue || "").trim();
    if (label) return label;

    const rawUrl = String(urlValue || "").trim();
    if (!rawUrl) return "Auto preview";

    const normalized = normalizeCandidateUrl(rawUrl);
    try {
      const url = new URL(normalized);
      let path = String(url.pathname || "");
      if (path === "/") path = "";
      if (path.endsWith("/")) path = path.slice(0, -1);
      let text = `${url.hostname.toLowerCase()}${path}`;
      if (text.length > 52) {
        text = `${text.slice(0, 49)}...`;
      }
      return text;
    } catch {
      return rawUrl;
    }
  };

  const updateRow = (row) => {
    if (!(row instanceof HTMLElement)) return;
    const labelInput = row.querySelector("[data-link-preview-label-input='1']");
    const urlInput = row.querySelector("[data-link-preview-url-input='1']");
    const icon = row.querySelector("[data-link-preview-icon='1']");
    const label = row.querySelector("[data-link-preview-label='1']");
    const providerText = row.querySelector("[data-link-preview-provider='1']");

    const labelValue = labelInput instanceof HTMLInputElement ? labelInput.value : "";
    const urlValue = urlInput instanceof HTMLInputElement ? urlInput.value : "";
    const provider = providerFromUrl(urlValue);
    const previewLabel = labelFromInputs(labelValue, urlValue);

    if (icon instanceof HTMLElement) {
      icon.className = `profileLinkPreview__icon profileLink__icon profileLink__icon--${provider}`;
      icon.innerHTML = icons[provider] || icons.website;
    }
    if (label instanceof HTMLElement) {
      label.textContent = previewLabel;
    }
    if (providerText instanceof HTMLElement) {
      providerText.textContent = providerNames[provider] || providerNames.website;
    }
  };

  rows.forEach((row) => {
    if (!(row instanceof HTMLElement)) return;
    const inputs = row.querySelectorAll("[data-link-preview-label-input='1'], [data-link-preview-url-input='1']");
    inputs.forEach((input) => {
      input.addEventListener("input", () => updateRow(row));
      input.addEventListener("change", () => updateRow(row));
    });
    updateRow(row);
  });
})();

(() => {
  const cards = Array.from(document.querySelectorAll("[data-profile-media-card]"));
  const modal = document.getElementById("profileCropperModal");
  if (!cards.length || !(modal instanceof HTMLElement)) return;

  const viewport = modal.querySelector("[data-profile-crop-viewport='1']");
  const stageImage = modal.querySelector("[data-profile-crop-image='1']");
  const avatarGuide = modal.querySelector("[data-profile-crop-avatar-guide='1']");
  const title = modal.querySelector("[data-profile-crop-title='1']");
  const subtitle = modal.querySelector("[data-profile-crop-subtitle='1']");
  const previewFrame = modal.querySelector("[data-profile-crop-preview-frame='1']");
  const previewImage = modal.querySelector("[data-profile-crop-preview-image='1']");
  const zoomInput = modal.querySelector("[data-profile-crop-zoom='1']");
  const resetButton = modal.querySelector("[data-profile-crop-reset='1']");
  const applyButton = modal.querySelector("[data-profile-crop-apply='1']");
  const closeButtons = modal.querySelectorAll(
    "[data-profile-crop-close='1'], [data-profile-crop-cancel='1']"
  );

  if (
    !(viewport instanceof HTMLElement) ||
    !(stageImage instanceof HTMLImageElement) ||
    !(avatarGuide instanceof HTMLElement) ||
    !(title instanceof HTMLElement) ||
    !(subtitle instanceof HTMLElement) ||
    !(previewFrame instanceof HTMLElement) ||
    !(previewImage instanceof HTMLImageElement) ||
    !(zoomInput instanceof HTMLInputElement) ||
    !(resetButton instanceof HTMLButtonElement) ||
    !(applyButton instanceof HTMLButtonElement)
  ) {
    return;
  }

  const mediaConfig = {
    avatar: {
      label: "Profile photo",
      cropTitle: "Crop profile photo",
      cropSubtitle:
        "Square crop. Drag the image to frame it, then zoom in if you want a tighter shot.",
      aspectRatio: 1,
      maxZoomPercent: 300,
      readyMessage: "Profile photo cropped. Save profile to publish it.",
      removedMessage: "Profile photo will be removed when you save.",
    },
    banner: {
      label: "Profile banner",
      cropTitle: "Crop profile banner",
      cropSubtitle:
        "Wide crop. Drag to choose the section you want across the top of your profile.",
      aspectRatio: 16 / 6,
      maxZoomPercent: 300,
      readyMessage: "Profile banner cropped. Save profile to publish it.",
      removedMessage: "Profile banner will be removed when you save.",
    },
  };

  const acceptedMimeTypes = new Set([
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
  ]);
  const mimeExtensions = {
    "image/jpeg": "jpg",
    "image/png": "png",
    "image/gif": "gif",
    "image/webp": "webp",
  };
  const mediaStates = new Map();
  let activeSession = null;

  const showToast = (message, type = "error") => {
    if (!message) return;
    if (typeof window.truxToast === "function") {
      window.truxToast(message, type);
    }
  };

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const clearInlinePreviewStyles = (image) => {
    if (!(image instanceof HTMLImageElement)) return;
    image.style.width = "";
    image.style.height = "";
    image.style.maxWidth = "";
    image.style.maxHeight = "";
    image.style.transform = "";
    image.style.transformOrigin = "";
  };

  const getElementSize = (element) => {
    if (!(element instanceof HTMLElement)) {
      return { width: 0, height: 0 };
    }

    return {
      width: element.clientWidth || element.offsetWidth || 0,
      height: element.clientHeight || element.offsetHeight || 0,
    };
  };

  const setStatus = (state, message = "") => {
    if (!state?.statusEl) return;
    const text = String(message || "").trim();
    state.statusEl.textContent = text;
    state.statusEl.hidden = text === "";
  };

  const renderCropIntoFrame = (
    frame,
    image,
    sourceUrl,
    naturalWidth,
    naturalHeight,
    crop
  ) => {
    if (
      !(frame instanceof HTMLElement) ||
      !(image instanceof HTMLImageElement) ||
      !sourceUrl ||
      !naturalWidth ||
      !naturalHeight ||
      !crop
    ) {
      return;
    }

    const size = getElementSize(frame);
    if (!size.width || !size.height) {
      return;
    }

    const cropWidth = Number(crop.width || 0);
    const cropHeight = Number(crop.height || 0);
    const cropX = Number(crop.x || 0);
    const cropY = Number(crop.y || 0);
    if (cropWidth <= 0 || cropHeight <= 0) {
      return;
    }

    const scale = Math.max(size.width / cropWidth, size.height / cropHeight);
    image.hidden = false;
    image.src = sourceUrl;
    image.style.width = `${naturalWidth * scale}px`;
    image.style.height = `${naturalHeight * scale}px`;
    image.style.maxWidth = "none";
    image.style.maxHeight = "none";
    image.style.transform = `translate(${-cropX * scale}px, ${-cropY * scale}px)`;
    image.style.transformOrigin = "top left";
  };

  const revokeObjectUrl = (url) => {
    if (typeof url === "string" && url.startsWith("blob:")) {
      URL.revokeObjectURL(url);
    }
  };

  const clearCommittedSelection = (state, options = {}) => {
    if (!state) return;

    const preserveStatus = options.preserveStatus === true;
    revokeObjectUrl(state.selectedUrl);
    state.selectedUrl = "";
    state.selectedNaturalWidth = 0;
    state.selectedNaturalHeight = 0;
    state.cropData = null;

    if (state.cropField instanceof HTMLInputElement) {
      state.cropField.value = "";
    }
    if (state.input instanceof HTMLInputElement) {
      state.input.value = "";
    }
    if (!preserveStatus) {
      setStatus(state, "");
    }
  };

  const renderMediaPreview = (state) => {
    if (!state) return;

    const hasCommittedSelection =
      state.selectedUrl !== "" &&
      state.selectedNaturalWidth > 0 &&
      state.selectedNaturalHeight > 0 &&
      state.cropData !== null &&
      !(state.removeCheckbox instanceof HTMLInputElement && state.removeCheckbox.checked);
    const showOriginal =
      !hasCommittedSelection &&
      state.originalSrc !== "" &&
      !(state.removeCheckbox instanceof HTMLInputElement && state.removeCheckbox.checked);

    if (state.previewFrame instanceof HTMLElement) {
      state.previewFrame.hidden = !(hasCommittedSelection || showOriginal);
    }

    if (state.emptyEl instanceof HTMLElement) {
      state.emptyEl.hidden = hasCommittedSelection || showOriginal;
    }

    if (state.recropButton instanceof HTMLButtonElement) {
      state.recropButton.hidden = false;
    }

    if (!(state.previewImage instanceof HTMLImageElement)) {
      return;
    }

    if (hasCommittedSelection) {
      renderCropIntoFrame(
        state.previewFrame,
        state.previewImage,
        state.selectedUrl,
        state.selectedNaturalWidth,
        state.selectedNaturalHeight,
        state.cropData
      );
      state.previewImage.alt = `${state.config.label} crop preview`;
      return;
    }

    clearInlinePreviewStyles(state.previewImage);

    if (showOriginal) {
      state.previewImage.hidden = false;
      state.previewImage.src = state.originalSrc;
      state.previewImage.alt = `Current ${state.config.label.toLowerCase()}`;
      return;
    }

    state.previewImage.hidden = true;
    state.previewImage.removeAttribute("src");
    state.previewImage.alt = "";
  };

  const readImageInfo = (url) =>
    new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => {
        if (image.naturalWidth > 0 && image.naturalHeight > 0) {
          resolve({
            width: image.naturalWidth,
            height: image.naturalHeight,
          });
          return;
        }
        reject(new Error("Could not read image dimensions."));
      };
      image.onerror = () => reject(new Error("Could not load selected image."));
      image.src = url;
    });

  const extensionFromSource = (sourceUrl, mimeType) => {
    const normalizedMime = String(mimeType || "").toLowerCase();
    if (mimeExtensions[normalizedMime]) {
      return mimeExtensions[normalizedMime];
    }

    try {
      const url = new URL(sourceUrl, window.location.href);
      const match = url.pathname.match(/\.([a-z0-9]+)$/i);
      if (match && typeof match[1] === "string") {
        const ext = match[1].toLowerCase();
        if (["jpg", "jpeg", "png", "gif", "webp"].includes(ext)) {
          return ext === "jpeg" ? "jpg" : ext;
        }
      }
    } catch {
      // Ignore URL parsing failures and fall back below.
    }

    return "png";
  };

  const buildExistingMediaFile = (state, blob) => {
    const extension = extensionFromSource(state.originalSrc, blob.type);
    const mimeType =
      blob.type && acceptedMimeTypes.has(blob.type)
        ? blob.type
        : `image/${extension === "jpg" ? "jpeg" : extension}`;

    return new File([blob], `${state.type}-crop-source.${extension}`, {
      type: mimeType,
      lastModified: Date.now(),
    });
  };

  const assignFileToInput = (input, file) => {
    if (!(input instanceof HTMLInputElement) || !(file instanceof File)) {
      return false;
    }
    if (typeof DataTransfer === "undefined") {
      return false;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    return input.files instanceof FileList && input.files.length === 1;
  };

  const clearReplaceAttempt = (state) => {
    clearCommittedSelection(state);
    renderMediaPreview(state);
  };

  const openFileCropper = async (state, file, options = {}) => {
    if (!(file instanceof File)) {
      return false;
    }

    const replacedCommittedSelection =
      options.replacedCommittedSelection === true || state.selectedUrl !== "";

    if (state.maxBytes > 0 && file.size > state.maxBytes) {
      if (state.input instanceof HTMLInputElement) {
        state.input.value = "";
      }
      if (replacedCommittedSelection) {
        clearReplaceAttempt(state);
      }
      showToast(`${state.config.label} must be 4MB or smaller.`, "error");
      return false;
    }

    if (file.type && !acceptedMimeTypes.has(file.type)) {
      if (state.input instanceof HTMLInputElement) {
        state.input.value = "";
      }
      if (replacedCommittedSelection) {
        clearReplaceAttempt(state);
      }
      showToast(`Unsupported file type for ${state.config.label.toLowerCase()}.`, "error");
      return false;
    }

    if (state.removeCheckbox.checked) {
      state.removeCheckbox.checked = false;
      setStatus(state, "");
    }

    const objectUrl = URL.createObjectURL(file);
    try {
      const info = await readImageInfo(objectUrl);
      openCropper(state, {
        mode: "new",
        sourceUrl: objectUrl,
        naturalWidth: info.width,
        naturalHeight: info.height,
        pendingUrl: objectUrl,
        replacedCommittedSelection,
        initialCrop: options.initialCrop ?? null,
      });
      return true;
    } catch (error) {
      revokeObjectUrl(objectUrl);
      if (state.input instanceof HTMLInputElement) {
        state.input.value = "";
      }
      if (replacedCommittedSelection) {
        clearReplaceAttempt(state);
      }
      showToast(
        error instanceof Error ? error.message : "Could not load selected image.",
        "error"
      );
      return false;
    }
  };

  const fetchExistingMediaIntoInput = async (state) => {
    if (!state.originalSrc) {
      return false;
    }

    let response;
    try {
      response = await fetch(state.originalSrc, {
        credentials: "same-origin",
      });
    } catch {
      showToast(`Could not load the current ${state.config.label.toLowerCase()}.`, "error");
      return false;
    }

    if (!response.ok) {
      showToast(`Could not load the current ${state.config.label.toLowerCase()}.`, "error");
      return false;
    }

    const blob = await response.blob();
    const file = buildExistingMediaFile(state, blob);
    const assigned = assignFileToInput(state.input, file);
    if (!assigned) {
      showToast(
        `Your browser could not prepare the current ${state.config.label.toLowerCase()} for recropping.`,
        "error"
      );
      return false;
    }

    return openFileCropper(state, file, {
      replacedCommittedSelection: false,
    });
  };

  const getViewportRect = (session, roundValues = false) => {
    const viewportSize = getElementSize(viewport);
    const scale = session?.scale || 0;
    if (!session || !viewportSize.width || !viewportSize.height || scale <= 0) {
      return null;
    }

    const x = Math.max(0, -session.x / scale);
    const y = Math.max(0, -session.y / scale);
    const width = Math.min(
      session.naturalWidth - x,
      viewportSize.width / scale
    );
    const height = Math.min(
      session.naturalHeight - y,
      viewportSize.height / scale
    );

    if (width <= 0 || height <= 0) {
      return null;
    }

    if (!roundValues) {
      return { x, y, width, height };
    }

    const roundedX = clamp(Math.round(x), 0, Math.max(0, session.naturalWidth - 1));
    const roundedY = clamp(Math.round(y), 0, Math.max(0, session.naturalHeight - 1));

    return {
      x: roundedX,
      y: roundedY,
      width: clamp(Math.round(width), 1, session.naturalWidth - roundedX),
      height: clamp(Math.round(height), 1, session.naturalHeight - roundedY),
    };
  };

  const clampSessionPosition = (session) => {
    const viewportSize = getElementSize(viewport);
    const renderedWidth = session.naturalWidth * session.scale;
    const renderedHeight = session.naturalHeight * session.scale;
    const minX = Math.min(0, viewportSize.width - renderedWidth);
    const minY = Math.min(0, viewportSize.height - renderedHeight);

    session.x = clamp(session.x, minX, 0);
    session.y = clamp(session.y, minY, 0);
  };

  const syncZoomInput = (session) => {
    const percent = Math.round((session.scale / session.minScale) * 100);
    zoomInput.value = String(clamp(percent, 100, session.config.maxZoomPercent));
  };

  const renderSession = (session) => {
    if (!session || activeSession !== session) return;

    const renderedWidth = session.naturalWidth * session.scale;
    const renderedHeight = session.naturalHeight * session.scale;

    stageImage.hidden = false;
    stageImage.src = session.sourceUrl;
    stageImage.style.width = `${renderedWidth}px`;
    stageImage.style.height = `${renderedHeight}px`;
    stageImage.style.maxWidth = "none";
    stageImage.style.maxHeight = "none";
    stageImage.style.transform = `translate(${session.x}px, ${session.y}px)`;
    stageImage.style.transformOrigin = "top left";

    const crop = getViewportRect(session, false);
    if (crop) {
      renderCropIntoFrame(
        previewFrame,
        previewImage,
        session.sourceUrl,
        session.naturalWidth,
        session.naturalHeight,
        crop
      );
    }

    syncZoomInput(session);
  };

  const setSessionScale = (session, nextScale, anchorX, anchorY) => {
    if (!session) return;

    const viewportSize = getElementSize(viewport);
    const focusX = Number.isFinite(anchorX) ? anchorX : viewportSize.width / 2;
    const focusY = Number.isFinite(anchorY) ? anchorY : viewportSize.height / 2;
    const boundedScale = clamp(nextScale, session.minScale, session.maxScale);

    const sourceFocusX = (focusX - session.x) / session.scale;
    const sourceFocusY = (focusY - session.y) / session.scale;

    session.scale = boundedScale;
    session.x = focusX - sourceFocusX * session.scale;
    session.y = focusY - sourceFocusY * session.scale;
    clampSessionPosition(session);
    renderSession(session);
  };

  const resetSession = (session, crop = null) => {
    if (!session) return;

    const viewportSize = getElementSize(viewport);
    if (!viewportSize.width || !viewportSize.height) {
      window.requestAnimationFrame(() => {
        if (activeSession === session) {
          resetSession(session, crop);
        }
      });
      return;
    }

    session.minScale = Math.max(
      viewportSize.width / session.naturalWidth,
      viewportSize.height / session.naturalHeight
    );
    session.maxScale = session.minScale * (session.config.maxZoomPercent / 100);

    if (
      crop &&
      Number(crop.width) > 0 &&
      Number(crop.height) > 0
    ) {
      const nextScale = Math.max(
        viewportSize.width / Number(crop.width),
        viewportSize.height / Number(crop.height)
      );
      session.scale = clamp(nextScale, session.minScale, session.maxScale);
      session.x = -Number(crop.x || 0) * session.scale;
      session.y = -Number(crop.y || 0) * session.scale;
    } else {
      session.scale = session.minScale;
      session.x = (viewportSize.width - session.naturalWidth * session.scale) / 2;
      session.y = (viewportSize.height - session.naturalHeight * session.scale) / 2;
    }

    clampSessionPosition(session);
    renderSession(session);
  };

  const openCropper = (state, sessionOptions) => {
    if (!state || !sessionOptions?.sourceUrl) return;

    const initialCrop =
      sessionOptions.mode === "existing" ? state.cropData : sessionOptions.initialCrop;

    activeSession = {
      state,
      config: state.config,
      mode: sessionOptions.mode,
      sourceUrl: sessionOptions.sourceUrl,
      naturalWidth: sessionOptions.naturalWidth,
      naturalHeight: sessionOptions.naturalHeight,
      pendingUrl: sessionOptions.pendingUrl || null,
      replacedCommittedSelection: sessionOptions.replacedCommittedSelection === true,
      scale: 1,
      minScale: 1,
      maxScale: 1,
      x: 0,
      y: 0,
      dragPointerId: null,
      dragStartX: 0,
      dragStartY: 0,
      startX: 0,
      startY: 0,
    };

    title.textContent = state.config.cropTitle;
    subtitle.textContent = state.config.cropSubtitle;
    avatarGuide.hidden = state.type !== "avatar";
    viewport.style.setProperty("--profile-crop-aspect", String(state.config.aspectRatio));
    previewFrame.classList.toggle("profileCropper__previewFrame--avatar", state.type === "avatar");
    previewFrame.classList.toggle("profileCropper__previewFrame--banner", state.type === "banner");
    modal.hidden = false;
    document.body.classList.add("profileCropper-open");
    previewFrame.hidden = false;
    previewImage.hidden = false;
    stageImage.alt = `${state.config.label} crop source`;
    previewImage.alt = `${state.config.label} crop preview`;

    window.requestAnimationFrame(() => {
      if (activeSession) {
        resetSession(activeSession, initialCrop);
      }
    });
  };

  const closeCropper = (options = {}) => {
    const session = activeSession;
    activeSession = null;

    viewport.classList.remove("is-dragging");
    modal.hidden = true;
    document.body.classList.remove("profileCropper-open");
    clearInlinePreviewStyles(stageImage);
    clearInlinePreviewStyles(previewImage);
    stageImage.hidden = true;
    previewImage.hidden = true;
    stageImage.removeAttribute("src");
    previewImage.removeAttribute("src");

    if (!session) return;

    if (session.pendingUrl) {
      revokeObjectUrl(session.pendingUrl);
    }

    if (!options.keepInput && session.mode === "new" && session.state.input instanceof HTMLInputElement) {
      session.state.input.value = "";
      if (session.replacedCommittedSelection) {
        clearCommittedSelection(session.state);
        renderMediaPreview(session.state);
      }
    }
  };

  const commitSessionCrop = () => {
    const session = activeSession;
    if (!session) return;

    const crop = getViewportRect(session, true);
    if (!crop) {
      showToast("Could not read the selected crop.", "error");
      return;
    }

    const state = session.state;
    const replacingCommittedUrl = state.selectedUrl;

    if (session.mode === "new" && replacingCommittedUrl && replacingCommittedUrl !== session.sourceUrl) {
      revokeObjectUrl(replacingCommittedUrl);
    }

    state.selectedUrl = session.sourceUrl;
    state.selectedNaturalWidth = session.naturalWidth;
    state.selectedNaturalHeight = session.naturalHeight;
    state.cropData = crop;

    if (state.cropField instanceof HTMLInputElement) {
      state.cropField.value = JSON.stringify(crop);
    }
    if (state.removeCheckbox instanceof HTMLInputElement) {
      state.removeCheckbox.checked = false;
    }

    setStatus(state, state.config.readyMessage);
    renderMediaPreview(state);

    session.pendingUrl = null;
    closeCropper({ keepInput: true });
  };

  closeButtons.forEach((button) => {
    button.addEventListener("click", () => {
      closeCropper();
    });
  });

  applyButton.addEventListener("click", () => {
    commitSessionCrop();
  });

  resetButton.addEventListener("click", () => {
    if (!activeSession) return;
    resetSession(activeSession);
  });

  zoomInput.addEventListener("input", () => {
    if (!activeSession) return;
    const percent = Number(zoomInput.value || "100");
    const nextScale = activeSession.minScale * (percent / 100);
    const viewportSize = getElementSize(viewport);
    setSessionScale(activeSession, nextScale, viewportSize.width / 2, viewportSize.height / 2);
  });

  viewport.addEventListener(
    "wheel",
    (event) => {
      if (!activeSession) return;
      event.preventDefault();

      const rect = viewport.getBoundingClientRect();
      const nextPercent = Number(zoomInput.value || "100") + (event.deltaY < 0 ? 8 : -8);
      zoomInput.value = String(
        clamp(nextPercent, 100, activeSession.config.maxZoomPercent)
      );
      const nextScale = activeSession.minScale * (Number(zoomInput.value) / 100);
      setSessionScale(
        activeSession,
        nextScale,
        event.clientX - rect.left,
        event.clientY - rect.top
      );
    },
    { passive: false }
  );

  viewport.addEventListener("pointerdown", (event) => {
    if (!activeSession || event.button !== 0) return;
    event.preventDefault();

    activeSession.dragPointerId = event.pointerId;
    activeSession.dragStartX = event.clientX;
    activeSession.dragStartY = event.clientY;
    activeSession.startX = activeSession.x;
    activeSession.startY = activeSession.y;
    viewport.classList.add("is-dragging");
    if (typeof viewport.setPointerCapture === "function") {
      viewport.setPointerCapture(event.pointerId);
    }
  });

  viewport.addEventListener("pointermove", (event) => {
    if (!activeSession || activeSession.dragPointerId !== event.pointerId) return;

    const deltaX = event.clientX - activeSession.dragStartX;
    const deltaY = event.clientY - activeSession.dragStartY;
    activeSession.x = activeSession.startX + deltaX;
    activeSession.y = activeSession.startY + deltaY;
    clampSessionPosition(activeSession);
    renderSession(activeSession);
  });

  const finishDrag = (event) => {
    if (!activeSession || activeSession.dragPointerId !== event.pointerId) return;
    activeSession.dragPointerId = null;
    viewport.classList.remove("is-dragging");
    if (typeof viewport.releasePointerCapture === "function") {
      viewport.releasePointerCapture(event.pointerId);
    }
  };

  viewport.addEventListener("pointerup", finishDrag);
  viewport.addEventListener("pointercancel", finishDrag);
  viewport.addEventListener("lostpointercapture", () => {
    viewport.classList.remove("is-dragging");
    if (activeSession) {
      activeSession.dragPointerId = null;
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || modal.hidden) return;
    closeCropper();
  });

  cards.forEach((card) => {
    if (!(card instanceof HTMLElement)) return;

    const type = card.getAttribute("data-profile-media-type") || "";
    const config = mediaConfig[type];
    const input = card.querySelector("[data-profile-media-input='1']");
    const cropField = card.querySelector("[data-profile-media-crop='1']");
    const previewFrameEl = card.querySelector("[data-profile-media-preview-frame='1']");
    const previewImageEl = card.querySelector("[data-profile-media-preview-image='1']");
    const emptyEl = card.querySelector("[data-profile-media-empty='1']");
    const recropButton = card.querySelector("[data-profile-media-recrop='1']");
    const statusEl = card.querySelector("[data-profile-media-status='1']");
    const removeCheckbox = card.querySelector("[data-profile-media-remove='1']");

    if (
      !config ||
      !(input instanceof HTMLInputElement) ||
      !(cropField instanceof HTMLInputElement) ||
      !(previewFrameEl instanceof HTMLElement) ||
      !(previewImageEl instanceof HTMLImageElement) ||
      !(emptyEl instanceof HTMLElement) ||
      !(recropButton instanceof HTMLButtonElement) ||
      !(statusEl instanceof HTMLElement) ||
      !(removeCheckbox instanceof HTMLInputElement)
    ) {
      return;
    }

    const state = {
      card,
      type,
      config,
      input,
      cropField,
      previewFrame: previewFrameEl,
      previewImage: previewImageEl,
      emptyEl,
      recropButton,
      statusEl,
      removeCheckbox,
      originalSrc: String(card.getAttribute("data-profile-original-src") || "").trim(),
      selectedUrl: "",
      selectedNaturalWidth: 0,
      selectedNaturalHeight: 0,
      cropData: null,
      maxBytes: Number(input.getAttribute("data-profile-max-bytes") || "0"),
    };

    mediaStates.set(type, state);
    renderMediaPreview(state);

    input.addEventListener("change", async () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) {
        return;
      }
      void openFileCropper(state, file, {
        replacedCommittedSelection: state.selectedUrl !== "",
      });
    });

    recropButton.addEventListener("click", async () => {
      if (removeCheckbox.checked) {
        removeCheckbox.checked = false;
        setStatus(state, "");
        renderMediaPreview(state);
      }

      if (
        state.selectedUrl &&
        state.selectedNaturalWidth > 0 &&
        state.selectedNaturalHeight > 0 &&
        state.cropData
      ) {
        openCropper(state, {
          mode: "existing",
          sourceUrl: state.selectedUrl,
          naturalWidth: state.selectedNaturalWidth,
          naturalHeight: state.selectedNaturalHeight,
        });
        return;
      }

      if (state.originalSrc) {
        await fetchExistingMediaIntoInput(state);
        return;
      }

      input.click();
    });

    removeCheckbox.addEventListener("change", () => {
      if (removeCheckbox.checked) {
        clearCommittedSelection(state, { preserveStatus: true });
        setStatus(state, state.config.removedMessage);
        renderMediaPreview(state);
        return;
      }

      setStatus(state, "");
      renderMediaPreview(state);
    });
  });

  window.addEventListener("resize", () => {
    mediaStates.forEach((state) => {
      renderMediaPreview(state);
    });

    if (!activeSession) return;
    const crop = getViewportRect(activeSession, false);
    resetSession(activeSession, crop);
  });
})();

(() => {
  const navItems = Array.from(document.querySelectorAll("[data-settings-nav]"));
  const sections = Array.from(document.querySelectorAll("[data-settings-section]"));
  if (!navItems.length || !sections.length) return;

  const setActive = (id) => {
    navItems.forEach((item) => {
      item.classList.toggle("is-active", item.getAttribute("data-settings-nav") === id);
    });
  };

  navItems.forEach((item) => {
    item.addEventListener("click", () => {
      const id = item.getAttribute("data-settings-nav") || "";
      if (id) setActive(id);
    });
  });

  const observer = new IntersectionObserver(
    (entries) => {
      let best = null;
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        if (!best || entry.intersectionRatio > best.intersectionRatio) {
          best = entry;
        }
      });

      if (best && best.target instanceof HTMLElement) {
        const id = best.target.getAttribute("data-settings-section") || "";
        if (id) setActive(id);
      }
    },
    {
      rootMargin: "-22% 0px -60% 0px",
      threshold: [0.2, 0.45, 0.7],
    }
  );

  sections.forEach((section) => {
    if (section instanceof HTMLElement) observer.observe(section);
  });

  const hash = window.location.hash.replace(/^#/, "");
  if (hash) {
    setActive(hash);
  }
})();

(() => {
  if (!window.location.href.includes("/bookmarks.php")) return;

  const STORAGE_KEY = "trux.bookmarks.scroll";
  const currentUrl = `${window.location.pathname}${window.location.search}`;

  const saveScrollState = (targetUrl) => {
    try {
      window.sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          target: targetUrl || currentUrl,
          scrollY: Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)),
        })
      );
    } catch {
      // Ignore storage failures.
    }
  };

  const readScrollState = () => {
    try {
      const raw = window.sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const data = JSON.parse(raw);
      if (!data || typeof data !== "object") return null;
      return {
        target:
          typeof data.target === "string" && data.target !== ""
            ? data.target
            : "",
        scrollY:
          typeof data.scrollY === "number" && Number.isFinite(data.scrollY)
            ? Math.max(0, data.scrollY)
            : 0,
      };
    } catch {
      return null;
    }
  };

  const clearScrollState = () => {
    try {
      window.sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      // Ignore storage failures.
    }
  };

  const restoreScrollState = () => {
    const saved = readScrollState();
    if (!saved || saved.target !== currentUrl) return;

    window.requestAnimationFrame(() => {
      window.scrollTo({ top: saved.scrollY, left: 0, behavior: "auto" });
    });

    window.setTimeout(() => {
      window.scrollTo({ top: saved.scrollY, left: 0, behavior: "auto" });
      clearScrollState();
    }, 80);
  };

  document.addEventListener("click", (e) => {
    const link = e.target.closest("a");
    if (!(link instanceof HTMLAnchorElement)) return;

    const href = link.getAttribute("href");
    if (!href) return;

    try {
      const url = new URL(href, window.location.origin);
      if (url.origin !== window.location.origin) return;
      if (!url.pathname.endsWith("/bookmarks.php")) return;
      saveScrollState(`${url.pathname}${url.search}`);
    } catch {
      // Ignore invalid URLs.
    }
  });

  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    saveScrollState(currentUrl);
  });

  window.addEventListener("pageshow", restoreScrollState);
  restoreScrollState();
})();

(() => {
  const states = new WeakMap();

  const getState = (pager) => {
    let state = states.get(pager);
    if (!state) {
      state = {
        loading: false,
        observer: null,
      };
      states.set(pager, state);
    }
    return state;
  };

  const escapeAttrValue = (value) =>
    String(value).replaceAll("\\", "\\\\").replaceAll('"', '\\"');

  const findList = (key, root = document) => {
    if (!key || !root || typeof root.querySelector !== "function") return null;
    return root.querySelector(`[data-auto-pager-list="${escapeAttrValue(key)}"]`);
  };

  const findPager = (key, root = document) => {
    if (!key || !root || typeof root.querySelector !== "function") return null;
    return root.querySelector(`[data-auto-pager="${escapeAttrValue(key)}"]`);
  };

  const getLink = (pager) => pager.querySelector("a[href]");

  const getLinkLabel = (link) => {
    if (!(link instanceof HTMLAnchorElement)) return "Load more";
    if (!link.dataset.autoPagerLabel) {
      link.dataset.autoPagerLabel = (link.textContent || "Load more").trim() || "Load more";
    }
    return link.dataset.autoPagerLabel;
  };

  const setLoading = (pager, loading) => {
    const link = getLink(pager);
    pager.classList.toggle("is-loading", loading);
    if (!(link instanceof HTMLAnchorElement)) return;

    const defaultLabel = getLinkLabel(link);
    link.textContent = loading ? "Loading older posts..." : defaultLabel;
    link.setAttribute("aria-disabled", loading ? "true" : "false");
    if (loading) {
      link.setAttribute("aria-busy", "true");
    } else {
      link.removeAttribute("aria-busy");
    }
  };

  const clearStatus = (pager) => {
    pager.classList.remove("is-error");
    const status = pager.querySelector("[data-auto-pager-status]");
    if (status instanceof HTMLElement) {
      status.remove();
    }
  };

  const setStatus = (pager, message) => {
    clearStatus(pager);
    if (!message) return;

    const status = document.createElement("span");
    status.className = "pager__status";
    status.setAttribute("data-auto-pager-status", "1");
    status.setAttribute("role", "status");
    status.textContent = String(message);
    pager.classList.add("is-error");
    pager.appendChild(status);
  };

  const stopObserving = (pager) => {
    const state = states.get(pager);
    if (
      typeof IntersectionObserver !== "undefined" &&
      state?.observer instanceof IntersectionObserver
    ) {
      state.observer.disconnect();
      state.observer = null;
    }
  };

  const announceContentAdded = (root) => {
    if (!(root instanceof HTMLElement)) return;

    document.dispatchEvent(
      new CustomEvent("trux:content-added", {
        detail: { root },
      })
    );

    if (typeof window.truxRefreshTimeAgo === "function") {
      window.truxRefreshTimeAgo();
    } else {
      window.dispatchEvent(new Event("trux:times:refresh"));
    }
  };

  const loadNextPage = async (pager) => {
    if (!(pager instanceof HTMLElement)) return;

    const state = getState(pager);
    if (state.loading) return;

    const key = (pager.getAttribute("data-auto-pager") || "").trim();
    const link = getLink(pager);
    const currentList = findList(key);
    if (!key || !(link instanceof HTMLAnchorElement) || !(currentList instanceof HTMLElement)) {
      stopObserving(pager);
      return;
    }

    state.loading = true;
    stopObserving(pager);
    clearStatus(pager);
    setLoading(pager, true);

    try {
      const res = await fetch(link.href, {
        headers: {
          Accept: "text/html",
        },
      });

      if (!res.ok) {
        throw new Error(`Request failed (${res.status}).`);
      }

      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextList = findList(key, doc);
      if (!(nextList instanceof HTMLElement)) {
        window.location.href = res.url || link.href;
        return;
      }

      const fragment = document.createDocumentFragment();
      Array.from(nextList.children).forEach((child) => {
        fragment.appendChild(document.importNode(child, true));
      });

      if (fragment.childNodes.length > 0) {
        currentList.appendChild(fragment);
        announceContentAdded(currentList);
      }

      const nextPager = findPager(key, doc);
      stopObserving(pager);
      states.delete(pager);

      if (nextPager instanceof HTMLElement) {
        const importedPager = document.importNode(nextPager, true);
        pager.replaceWith(importedPager);
        initAutoPager(importedPager);
      } else {
        pager.remove();
      }
    } catch (err) {
      state.loading = false;
      setLoading(pager, false);
      setStatus(
        pager,
        err instanceof Error ? err.message : "Could not load older posts."
      );
      observePager(pager);
      if (typeof window.truxToast === "function") {
        window.truxToast("Could not load older posts.", "error");
      }
    }
  };

  const observePager = (pager) => {
    if (!(pager instanceof HTMLElement) || !("IntersectionObserver" in window)) return;

    const state = getState(pager);
    stopObserving(pager);
    state.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            void loadNextPage(pager);
          }
        });
      },
      {
        rootMargin: "480px 0px 220px 0px",
      }
    );
    state.observer.observe(pager);
  };

  const initAutoPager = (pager) => {
    if (!(pager instanceof HTMLElement)) return;
    if (pager.dataset.autoPagerReady === "1") return;

    const key = (pager.getAttribute("data-auto-pager") || "").trim();
    if (!key || !(findList(key) instanceof HTMLElement)) return;

    pager.dataset.autoPagerReady = "1";
    pager.addEventListener("click", (e) => {
      const link = e.target.closest("a[href]");
      if (!(link instanceof HTMLAnchorElement)) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (link.target && link.target !== "_self") return;

      e.preventDefault();
      void loadNextPage(pager);
    });

    observePager(pager);
  };

  document.querySelectorAll("[data-auto-pager]").forEach((pager) => {
    initAutoPager(pager);
  });
})();

(() => {
  const reviewModals = Array.from(
    document.querySelectorAll("[data-review-modal='1']")
  ).filter((modal) => modal instanceof HTMLElement);
  if (!reviewModals.length) return;

  const modalMap = new Map();
  reviewModals.forEach((modal) => {
    if (!(modal instanceof HTMLElement) || !modal.id) return;
    if (document.body && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    modalMap.set(modal.id, modal);
  });

  let activeReviewModal = null;
  let reviewReturnFocus = null;

  const applyModalResetUrl = (modal) => {
    if (!(modal instanceof HTMLElement)) return;
    const resetUrl = modal.getAttribute("data-review-modal-reset-url") || "";
    if (!resetUrl || !window.history || typeof window.history.replaceState !== "function") {
      return;
    }

    try {
      window.history.replaceState({}, "", resetUrl);
    } catch {
      // Ignore URL reset failures and still close the modal.
    }
  };

  const closeReviewModal = (options = {}) => {
    if (!(activeReviewModal instanceof HTMLElement)) return;

    const shouldRestoreFocus = options.restoreFocus !== false;
    const closingModal = activeReviewModal;
    activeReviewModal.setAttribute("hidden", "hidden");
    document.body.classList.remove("reviewModal-open");
    applyModalResetUrl(closingModal);

    if (
      shouldRestoreFocus &&
      reviewReturnFocus &&
      typeof reviewReturnFocus.focus === "function"
    ) {
      reviewReturnFocus.focus();
    }

    activeReviewModal = null;
    reviewReturnFocus = null;
  };

  const openReviewModal = (modalId, trigger = null) => {
    const modal = modalMap.get(String(modalId || ""));
    if (!(modal instanceof HTMLElement)) {
      return false;
    }

    if (activeReviewModal && activeReviewModal !== modal) {
      closeReviewModal({ restoreFocus: false });
    }

    reviewReturnFocus = trigger instanceof HTMLElement ? trigger : null;
    activeReviewModal = modal;
    modal.removeAttribute("hidden");
    document.body.classList.add("reviewModal-open");

    window.setTimeout(() => {
      const focusTarget = modal.querySelector(
        "input:not([type='hidden']), select, textarea, button"
      );
      if (focusTarget instanceof HTMLElement) {
        focusTarget.focus();
      }
    }, 20);

    return true;
  };

  document.addEventListener("click", (e) => {
    if (!(e.target instanceof Element)) return;

    const closeTrigger = e.target.closest("[data-review-modal-close='1']");
    if (closeTrigger instanceof HTMLElement) {
      e.preventDefault();
      closeReviewModal();
      return;
    }

    const openTrigger = e.target.closest("[data-review-modal-open]");
    if (openTrigger instanceof HTMLElement) {
      const modalId = openTrigger.getAttribute("data-review-modal-open") || "";
      if (!modalId) return;
      e.preventDefault();
      openReviewModal(modalId, openTrigger);
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (!(activeReviewModal instanceof HTMLElement)) return;
    closeReviewModal();
  });

  const autoOpenModal = document.querySelector("[data-review-modal-autopen='1']");
  if (autoOpenModal instanceof HTMLElement && autoOpenModal.id) {
    openReviewModal(autoOpenModal.id);
  }
})();

(() => {
  const reportModal = document.getElementById("postReportModal");
  if (!(reportModal instanceof HTMLElement)) return;

  const reportForm = reportModal.querySelector("[data-report-form='1']");
  const reportTargetTypeField = reportModal.querySelector("[data-report-target-type='1']");
  const reportTargetIdField = reportModal.querySelector("[data-report-target-id='1']");
  const reportTargetText = reportModal.querySelector("[data-report-target-text='1']");
  const reportOpenLink = reportModal.querySelector("[data-report-open-link='1']");
  const reportFlash = reportModal.querySelector("[data-report-flash='1']");
  const reportReasonField = reportModal.querySelector("[data-report-reason='1']");
  const reportDetailsField = reportModal.querySelector("[data-report-details='1']");
  const reportUpdatesField = reportModal.querySelector("[data-report-updates='1']");
  const reportSubmitBtn = reportModal.querySelector("[data-report-submit='1']");

  if (
    !(reportForm instanceof HTMLFormElement) ||
    !(reportTargetTypeField instanceof HTMLInputElement) ||
    !(reportTargetIdField instanceof HTMLInputElement) ||
    !(reportTargetText instanceof HTMLElement) ||
    !(reportOpenLink instanceof HTMLAnchorElement) ||
    !(reportFlash instanceof HTMLElement) ||
    !(reportReasonField instanceof HTMLSelectElement) ||
    !(reportDetailsField instanceof HTMLTextAreaElement) ||
    !(reportUpdatesField instanceof HTMLInputElement) ||
    !(reportSubmitBtn instanceof HTMLButtonElement)
  ) {
    return;
  }

  let reportReturnFocus = null;

  const readJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { ok: false, error: text || "Request failed." };
    }
  };

  const getCsrfToken = () => {
    const input = document.querySelector("input[name='_csrf']");
    return input instanceof HTMLInputElement ? input.value : "";
  };

  const resolveAbsoluteUrl = (value) => {
    try {
      return new URL(String(value || "/"), window.location.origin).toString();
    } catch {
      return window.location.href;
    }
  };

  const closeAllContentMenus = () => {
    document.querySelectorAll("[data-content-menu='1']").forEach((menu) => {
      if (!(menu instanceof HTMLElement)) return;
      menu.classList.remove("is-open");
      const post = menu.closest(".post");
      if (post instanceof HTMLElement) {
        post.classList.remove("post--menuOpen");
      }
    });
  };

  const setReportFlash = (message = "") => {
    const text = String(message || "").trim();
    if (!text) {
      reportFlash.hidden = true;
      reportFlash.textContent = "";
      return;
    }
    reportFlash.hidden = false;
    reportFlash.textContent = text;
  };

  const isReportOpen = () => !reportModal.hasAttribute("hidden");

  const setReportBusy = (busy) => {
    reportSubmitBtn.disabled = !!busy;
    reportReasonField.disabled = !!busy;
    reportDetailsField.readOnly = !!busy;
    reportUpdatesField.disabled = !!busy;
  };

  const openReportModal = (options = {}) => {
    const targetType = String(options.targetType || "").trim().toLowerCase();
    const targetId = Number(options.targetId || 0);
    const targetLabel = String(options.targetLabel || "").trim();
    const openUrl = resolveAbsoluteUrl(options.openUrl || `${window.TRUX_BASE_URL || ""}/`);
    const trigger = options.trigger;
    if (!targetType || targetId <= 0) {
      return false;
    }

    reportReturnFocus =
      trigger instanceof HTMLElement || trigger instanceof HTMLButtonElement
        ? trigger
        : null;

    reportForm.reset();
    reportTargetTypeField.value = targetType;
    reportTargetIdField.value = String(targetId);
    reportTargetText.textContent = targetLabel || `${targetType} #${targetId}`;
    reportOpenLink.href = openUrl;
    setReportFlash("");
    setReportBusy(false);
    reportModal.removeAttribute("hidden");
    document.body.classList.add("reportModal-open");

    window.setTimeout(() => {
      reportReasonField.focus();
    }, 20);

    return true;
  };

  const closeReportModal = (options = {}) => {
    if (!isReportOpen()) return;

    const shouldRestoreFocus = options.restoreFocus !== false;
    reportModal.setAttribute("hidden", "hidden");
    document.body.classList.remove("reportModal-open");
    reportForm.reset();
    reportTargetTypeField.value = "";
    reportTargetIdField.value = "";
    reportTargetText.textContent = "Content";
    reportOpenLink.href = `${window.TRUX_BASE_URL || ""}/`;
    setReportFlash("");
    setReportBusy(false);

    if (
      shouldRestoreFocus &&
      reportReturnFocus &&
      typeof reportReturnFocus.focus === "function"
    ) {
      reportReturnFocus.focus();
    }
    reportReturnFocus = null;
  };

  document.addEventListener("click", (e) => {
    if (!(e.target instanceof Element)) return;

    const closeTrigger = e.target.closest(
      "[data-report-close='1'], [data-report-cancel='1']"
    );
    if (closeTrigger instanceof HTMLElement) {
      e.preventDefault();
      closeReportModal();
      return;
    }

    const reportBtn = e.target.closest("[data-report-action='1']");
    if (reportBtn instanceof HTMLElement) {
      e.preventDefault();
      closeAllContentMenus();

      const targetType = reportBtn.getAttribute("data-report-target-type") || "";
      const targetId = Number(reportBtn.getAttribute("data-report-target-id") || "0");
      const targetLabel = reportBtn.getAttribute("data-report-target-label") || "";
      const openUrl = reportBtn.getAttribute("data-report-open-url") || "";

      if (!openReportModal({ targetType, targetId, targetLabel, openUrl, trigger: reportBtn })) {
        window.alert("Reporting is unavailable right now.");
      }
    }
  });

  reportForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const targetType = reportTargetTypeField.value.trim().toLowerCase();
    const targetId = Number(reportTargetIdField.value || "0");
    const reasonKey = reportReasonField.value.trim();
    const details = reportDetailsField.value.trim();
    const wantsReporterDmUpdates = !!reportUpdatesField.checked;

    if (!targetType || targetId <= 0) {
      setReportFlash("Action unavailable right now.");
      return;
    }

    if (!reasonKey) {
      setReportFlash("Choose a violation before submitting.");
      reportReasonField.focus();
      return;
    }

    const csrf = getCsrfToken();
    if (!csrf) {
      setReportFlash("Session expired. Please refresh the page and try again.");
      return;
    }

    const body = new URLSearchParams({
      _csrf: csrf,
      target_type: targetType,
      target_id: String(targetId),
      reason_key: reasonKey,
      details,
    });
    if (wantsReporterDmUpdates) {
      body.append("wants_reporter_dm_updates", "1");
    }

    setReportFlash("");
    setReportBusy(true);

    try {
      const res = await fetch(
        `${window.TRUX_BASE_URL || ""}/report.php?format=json`,
        {
          method: "POST",
          headers: { Accept: "application/json" },
          body,
        }
      );
      const data = await readJson(res);
      if (res.status === 401 && data.login_url) {
        window.location.href = String(data.login_url);
        return;
      }
      if (!res.ok || !data.ok) {
        throw new Error(data.error || "Could not submit the report.");
      }

      closeReportModal();
      if (typeof window.truxToast === "function") {
        window.truxToast(
          String(data.message || "Report submitted."),
          "success"
        );
      }
    } catch (err) {
      setReportFlash(
        err instanceof Error ? err.message : "Could not submit the report."
      );
    } finally {
      if (isReportOpen()) {
        setReportBusy(false);
      }
    }
  });

  window.truxOpenReportModal = openReportModal;
})();
