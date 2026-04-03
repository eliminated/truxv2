(() => {
  if (
    window.truxTimeAgoBooted === true &&
    typeof window.truxRefreshTimeAgo === "function"
  ) {
    return;
  }

  const TIME_SELECTOR = "[data-time-ago='1'][data-time-source]";

  const parseTimeSource = (raw) => {
    if (!raw || typeof raw !== "string") return null;
    const source = raw.trim();
    if (!source) return null;

    const mysqlish = source.match(
      /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/
    );
    if (mysqlish) {
      const dt = new Date(
        Number(mysqlish[1]),
        Number(mysqlish[2]) - 1,
        Number(mysqlish[3]),
        Number(mysqlish[4] || "0"),
        Number(mysqlish[5] || "0"),
        Number(mysqlish[6] || "0")
      );
      return Number.isNaN(dt.getTime()) ? null : dt;
    }

    const dt = new Date(source);
    return Number.isNaN(dt.getTime()) ? null : dt;
  };

  const formatAgo = (diffSec) => {
    let safeDiff = Number(diffSec);
    if (!Number.isFinite(safeDiff) || safeDiff < 0) safeDiff = 0;
    if (safeDiff < 10) return "just now";
    if (safeDiff < 60) return `${safeDiff} seconds ago`;

    const mins = Math.floor(safeDiff / 60);
    if (mins < 60) return `${mins} minute${mins === 1 ? "" : "s"} ago`;

    const hours = Math.floor(safeDiff / 3600);
    if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;

    const days = Math.floor(safeDiff / 86400);
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

  const boot = () => {
    window.setTimeout(refreshTimeAgo, 1000);
    window.setInterval(refreshTimeAgo, 30000);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }

  window.addEventListener("trux:times:refresh", refreshTimeAgo);
  window.truxRefreshTimeAgo = refreshTimeAgo;
  window.truxTimeAgoBooted = true;
})();

(() => {
  const body = document.body;
  if (!(body instanceof HTMLElement)) return;

  const src =
    body.dataset.notificationCountSrc ||
    (typeof window.TRUX_NOTIFICATION_COUNT_URL === "string"
      ? window.TRUX_NOTIFICATION_COUNT_URL
      : "");
  if (!src) return;

  const formatBadge = (count) => {
    const safeCount = Number(count);
    if (!Number.isFinite(safeCount) || safeCount <= 0) {
      return { show: false, label: "0", subtitle: "All caught up" };
    }
    const label = safeCount > 99 ? "99+" : String(safeCount);
    return {
      show: true,
      label,
      subtitle: `${label} unread`,
    };
  };

  const applyBadgeState = (selector, count) => {
    const badge = formatBadge(count);
    document.querySelectorAll(selector).forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      node.textContent = badge.label;
      node.hidden = !badge.show;
    });
    return badge.subtitle;
  };

  const applyUnreadState = ({ notifications, messages }) => {
    const notificationSubtitle = applyBadgeState(
      "[data-notification-unread-badge]",
      notifications
    );
    document
      .querySelectorAll("[data-notification-unread-label]")
      .forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        node.textContent = notificationSubtitle;
      });

    applyBadgeState("[data-message-unread-badge]", messages);
  };

  window
    .fetch(src, {
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
    .then(async (response) => {
      if (!response.ok) {
        throw new Error(`Unread count request failed: ${response.status}`);
      }
      return response.json();
    })
    .then((payload) => {
      applyUnreadState({
        notifications: Number(payload?.notifications || 0),
        messages: Number(payload?.messages || 0),
      });
    })
    .catch(() => {
      document
        .querySelectorAll("[data-notification-unread-label]")
        .forEach((node) => {
          if (!(node instanceof HTMLElement)) return;
          node.textContent = "Unread status unavailable right now.";
        });
    });
})();

(() => {
  const feed = document.querySelector(
    "[data-post-interactions-feed='1'][data-post-interactions-src]"
  );
  if (!(feed instanceof HTMLElement)) return;

  const src = feed.getAttribute("data-post-interactions-src") || "";
  const csrf =
    typeof window.TRUX_CSRF_TOKEN === "string" ? window.TRUX_CSRF_TOKEN : "";
  if (!src || !csrf) return;

  if (window.performance?.mark) {
    window.performance.mark("trux-feed-rendered");
  }

  const BATCH_SIZE = 5;
  const queuedIds = [];
  const queuedSet = new Set();
  const loadedSet = new Set();
  const cache = new Map();
  let flushTimer = 0;
  let loading = false;
  let hydrationMarked = false;

  const setCount = (kind, postId, count) => {
    const selectorByKind = {
      like: `[data-like-count-for="${postId}"]`,
      comment: `[data-comment-count-for="${postId}"]`,
      share: `[data-share-count-for="${postId}"]`,
      bookmark: `[data-bookmark-count-for="${postId}"]`,
    };
    const selector = selectorByKind[kind];
    if (!selector) return;

    document.querySelectorAll(selector).forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      node.textContent = String(count);
      node.classList.remove("is-placeholder");
      node.removeAttribute("data-count-placeholder");
      node.removeAttribute("aria-busy");
    });
  };

  const setOwnerBookmarkState = (postId, active) => {
    document
      .querySelectorAll(
        `[data-owner-bookmark="1"][data-owner-type="post"][data-owner-id="${postId}"]`
      )
      .forEach((node) => {
        if (!(node instanceof HTMLButtonElement)) return;
        node.classList.toggle("is-active", !!active);
        node.setAttribute(
          "aria-label",
          active ? "Remove bookmark" : "Bookmark"
        );
        const label = node.querySelector("[data-owner-bookmark-label='1']");
        if (label instanceof HTMLElement) {
          label.textContent = active ? "Saved" : "Bookmark";
        }
      });
  };

  const setActiveState = (kind, postId, active) => {
    document
      .querySelectorAll(
        `form[data-ajax-action="1"][data-action-kind="${kind}"][data-post-id="${postId}"] .postAct`
      )
      .forEach((button) => {
        if (!(button instanceof HTMLElement)) return;
        button.classList.toggle("is-active", !!active);
        if (kind === "like") {
          button.setAttribute("aria-label", active ? "Unlike post" : "Like post");
        } else if (kind === "share") {
          button.setAttribute("aria-label", active ? "Unshare post" : "Share post");
        } else if (kind === "bookmark") {
          button.setAttribute(
            "aria-label",
            active ? "Remove bookmark" : "Bookmark post"
          );
          const label = button.querySelector("[data-action-label='bookmark']");
          if (label instanceof HTMLElement) {
            label.textContent = active ? "Saved" : "Bookmark";
          }
          setOwnerBookmarkState(postId, active);
        }
      });
  };

  const markLoaded = (postId) => {
    document
      .querySelectorAll(`article[data-post-id="${postId}"]`)
      .forEach((article) => {
        if (!(article instanceof HTMLElement)) return;
        article.dataset.postInteractionsState = "loaded";
        article.removeAttribute("data-needs-interactions");
      });
  };

  const markError = (postId) => {
    document
      .querySelectorAll(`article[data-post-id="${postId}"][data-post-interactions-state]`)
      .forEach((article) => {
        if (!(article instanceof HTMLElement)) return;
        article.dataset.postInteractionsState = "error";
      });
  };

  const applyInteractionState = (postId, data) => {
    const payload = data && typeof data === "object" ? data : {};
    setCount("like", postId, Number(payload.likes || 0));
    setCount("comment", postId, Number(payload.comments || 0));
    setCount("share", postId, Number(payload.shares || 0));
    setCount("bookmark", postId, Number(payload.bookmarks || 0));
    setActiveState("like", postId, !!payload.liked);
    setActiveState("share", postId, !!payload.shared);
    setActiveState("bookmark", postId, !!payload.bookmarked);
    markLoaded(postId);
  };

  const flushQueue = async () => {
    if (loading || queuedIds.length === 0) return;
    loading = true;

    const batch = queuedIds.splice(0, BATCH_SIZE);
    batch.forEach((id) => queuedSet.delete(id));

    const params = new URLSearchParams();
    params.set("_csrf", csrf);
    batch.forEach((id) => {
      params.append("post_ids[]", String(id));
    });

    try {
      const response = await window.fetch(src, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: params.toString(),
      });

      const payload = await response.json();
      if (!response.ok || !payload?.ok || typeof payload.interactions !== "object") {
        throw new Error("Interaction payload unavailable.");
      }

      batch.forEach((postId) => {
        const interactionState =
          payload.interactions[String(postId)] || payload.interactions[postId] || {};
        cache.set(postId, interactionState);
        loadedSet.add(postId);
        applyInteractionState(postId, interactionState);
      });

      if (!hydrationMarked && window.performance?.mark) {
        hydrationMarked = true;
        window.performance.mark("trux-feed-interactions-hydrated");
      }
    } catch {
      batch.forEach((postId) => {
        markError(postId);
      });
    } finally {
      loading = false;
      if (queuedIds.length > 0) {
        flushTimer = window.setTimeout(flushQueue, 40);
      }
    }
  };

  const queuePost = (postId) => {
    if (!postId || loadedSet.has(postId) || queuedSet.has(postId)) return;
    queuedSet.add(postId);
    queuedIds.push(postId);
    if (flushTimer) {
      window.clearTimeout(flushTimer);
    }
    flushTimer = window.setTimeout(flushQueue, 60);
  };

  const hydrateArticle = (article) => {
    if (!(article instanceof HTMLElement)) return;
    const postId = Number(article.getAttribute("data-post-id") || "0");
    if (!postId) return;

    if (cache.has(postId)) {
      applyInteractionState(postId, cache.get(postId));
      loadedSet.add(postId);
      return;
    }

    article.dataset.postInteractionsState = "queued";
    queuePost(postId);
  };

  const observeArticles = (root = document) => {
    const scope =
      root instanceof Document || root instanceof HTMLElement ? root : document;
    const articles = scope.querySelectorAll(
      "article[data-needs-interactions='1'][data-post-id]"
    );

    if (typeof window.IntersectionObserver === "undefined") {
      articles.forEach((article) => hydrateArticle(article));
      return;
    }

    const observer =
      observeArticles.observer ||
      new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            hydrateArticle(entry.target);
          });
        },
        { rootMargin: "280px 0px 280px 0px" }
      );
    observeArticles.observer = observer;

    articles.forEach((article) => {
      if (!(article instanceof HTMLElement)) return;
      const postId = Number(article.getAttribute("data-post-id") || "0");
      if (!postId) return;
      if (cache.has(postId)) {
        applyInteractionState(postId, cache.get(postId));
        loadedSet.add(postId);
        return;
      }
      observer.observe(article);
    });
  };

  observeArticles(feed);
  document.addEventListener("trux:content-added", (event) => {
    const root =
      event instanceof CustomEvent &&
      event.detail &&
      event.detail.root instanceof HTMLElement
        ? event.detail.root
        : document;
    observeArticles(root);
  });
})();
