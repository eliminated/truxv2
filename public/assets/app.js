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
  const empty = dock.querySelector("[data-comment-empty]");
  const form = dock.querySelector("[data-comment-form]");
  const postIdField = dock.querySelector("[data-comment-post-id]");
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

  const renderComments = (comments) => {
    if (!list || !empty) return;
    list.innerHTML = "";

    if (!Array.isArray(comments) || comments.length === 0) {
      empty.hidden = false;
      return;
    }

    empty.hidden = true;
    comments.forEach((c) => {
      const item = document.createElement("article");
      item.className = "commentDock__item";
      item.innerHTML = `
        <div class="commentDock__meta">
          <a class="commentDock__user" href="/profile.php?u=${encodeURIComponent(c.username)}">@${esc(c.username)}</a>
          <span class="commentDock__time" title="${esc(c.exact_time)}">${esc(c.time_ago)}</span>
        </div>
        <div class="commentDock__body">${esc(c.body).replaceAll("\n", "<br>")}</div>
      `;
      list.appendChild(item);
    });
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
    loadComments(postId);

    const input = form ? form.querySelector("textarea[name='body']") : null;
    if (input instanceof HTMLTextAreaElement) {
      window.setTimeout(() => input.focus(), 30);
    }
  };

  const closeDock = () => {
    if (!isOpen()) return;
    dock.setAttribute("hidden", "hidden");
    document.body.classList.remove("commentDock-open");
    currentPostId = 0;
    if (postIdField) postIdField.value = "";
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
