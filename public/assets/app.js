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

  const isOpen = () => !dock.hasAttribute("hidden");

  const setCommentCount = (postId, count) => {
    const nodes = document.querySelectorAll(`[data-comment-count-for="${postId}"]`);
    nodes.forEach((n) => {
      n.textContent = String(count);
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
        <span
          class="commentDock__time"
          data-time-ago="1"
          data-time-source="${esc(c.created_at)}"
          title="${esc(c.exact_time)}">${esc(c.time_ago)}</span>
      </div>
      <div class="commentDock__body">${esc(c.body).replaceAll("\n", "<br>")}</div>
      <div class="commentDock__actions">
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

  document.addEventListener("click", (e) => {
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

    const replyCancelBtn = e.target.closest("[data-comment-reply-cancel]");
    if (replyCancelBtn instanceof HTMLElement) {
      e.preventDefault();
      clearReplyState();
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
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
