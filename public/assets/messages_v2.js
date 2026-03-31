(() => {
  if (!window.location.href.includes("/messages.php")) return;

  const baseUrl = window.TRUX_BASE_URL || "";
  const layout = document.querySelector("[data-messages-layout='1']");
  if (!(layout instanceof HTMLElement)) return;

  const body = document.body;
  const searchInput = layout.querySelector("[data-conversation-search='1']");
  const emptyState = layout.querySelector("[data-conversation-empty='1']");
  const recipientInput = document.querySelector("[data-message-recipient-input='1']");
  const recipientStatus = document.querySelector("[data-message-recipient-status='1']");
  const recipientResults = document.querySelector("[data-message-recipient-results='1']");
  const recipientOpenButtons = Array.from(
    layout.querySelectorAll("[data-new-message-open='1']")
  ).filter((button) => button instanceof HTMLElement);
  const mobileQuery =
    typeof window.matchMedia === "function"
      ? window.matchMedia("(max-width: 768px)")
      : null;
  const finePointerQuery =
    typeof window.matchMedia === "function"
      ? window.matchMedia("(pointer: fine)")
      : null;
  const defaultRecipientStatus =
    recipientStatus instanceof HTMLElement
      ? String(recipientStatus.textContent || "").trim()
      : "";

  const state = {
    recipientTimer: 0,
    recipientAbort: null,
    threadAbort: null,
    threadRequestId: 0,
    conversationFilterFrame: 0,
    pollTimer: 0,
    pollInFlight: false,
    readTimer: 0,
    pendingRead: false,
    loadingOlder: false,
    selectedAttachments: [],
    attachmentCounter: 0,
    replyContext: null,
    emojiCategory: "smileys",
    knownMessageIds: new Set(),
    latestMessageId: 0,
    oldestMessageId: 0,
    hasMoreBefore: false,
    jumpCount: 0,
    tempMessageCounter: 0,
    failedMessages: new Map(),
    editingMessageId: "",
    editingRestoreHtml: "",
    threadTransportRetryUrl: "",
    quickActionsBubbleId: "",
    quickActionsHideTimer: 0,
    quickActionsMode: "",
    longPressTimer: 0,
    longPressBubbleId: "",
  };

  const MAX_CLIENT_ATTACHMENT_BYTES = 4 * 1024 * 1024;
  const EMOJI_RECENT_STORAGE_KEY = "trux.dm.emoji.recent";
  const EMOJI_CATEGORIES = [
    {
      id: "smileys",
      icon: "😀",
      items: [
        ["😀", "grinning happy smile"],
        ["😁", "beaming grin smile"],
        ["😂", "joy tears laugh"],
        ["🤣", "rofl laugh rolling"],
        ["😃", "smile open happy"],
        ["😄", "happy grin bright"],
        ["😅", "sweat smile relief"],
        ["😆", "laughing squint grin"],
        ["😉", "wink playful"],
        ["😊", "blush smile warm"],
        ["😍", "heart eyes love"],
        ["😘", "kiss face"],
        ["😎", "cool sunglasses"],
        ["🤩", "star struck wow"],
        ["😇", "angel halo"],
        ["🙂", "slight smile"],
        ["🙃", "upside down playful"],
        ["😌", "relieved calm"],
        ["😋", "yum delicious"],
        ["😜", "tongue wink silly"],
        ["🤔", "thinking curious"],
        ["😴", "sleepy tired"],
        ["😮", "surprised wow"],
        ["🥳", "party celebrate"],
      ],
    },
    {
      id: "people",
      icon: "👋",
      items: [
        ["👋", "wave hello goodbye"],
        ["🤚", "raised hand stop"],
        ["🖐️", "splayed hand"],
        ["✋", "hand high five"],
        ["👌", "ok hand"],
        ["🤌", "pinched fingers"],
        ["🤏", "pinching hand small"],
        ["✌️", "victory peace"],
        ["🤞", "crossed fingers luck"],
        ["🫶", "heart hands love"],
        ["🤟", "love you hand"],
        ["🤘", "rock sign"],
        ["👏", "clap applause"],
        ["🙌", "raised hands celebrate"],
        ["🫡", "salute respect"],
        ["🙏", "pray thanks please"],
        ["💪", "muscle strong"],
        ["🧠", "brain smart"],
        ["👀", "eyes looking"],
        ["🫂", "hug support"],
        ["🧑‍💻", "person laptop coder"],
        ["🧑‍🚀", "astronaut space"],
        ["🕵️", "detective spy"],
        ["🥷", "ninja stealth"],
      ],
    },
    {
      id: "animals",
      icon: "🐶",
      items: [
        ["🐶", "dog pet puppy"],
        ["🐱", "cat pet kitten"],
        ["🐭", "mouse"],
        ["🐹", "hamster"],
        ["🐰", "rabbit bunny"],
        ["🦊", "fox"],
        ["🐻", "bear"],
        ["🐼", "panda"],
        ["🐨", "koala"],
        ["🐯", "tiger"],
        ["🦁", "lion"],
        ["🐮", "cow"],
        ["🐷", "pig"],
        ["🐸", "frog"],
        ["🐵", "monkey"],
        ["🐔", "chicken"],
        ["🐧", "penguin"],
        ["🦄", "unicorn"],
        ["🐙", "octopus"],
        ["🦋", "butterfly"],
        ["🐬", "dolphin"],
        ["🦈", "shark"],
        ["🐢", "turtle"],
        ["🐝", "bee"],
      ],
    },
    {
      id: "food",
      icon: "🍕",
      items: [
        ["🍎", "apple fruit"],
        ["🍉", "watermelon fruit"],
        ["🍇", "grapes fruit"],
        ["🍓", "strawberry fruit"],
        ["🍒", "cherries fruit"],
        ["🥑", "avocado"],
        ["🌮", "taco"],
        ["🍔", "burger hamburger"],
        ["🍟", "fries chips"],
        ["🍕", "pizza slice"],
        ["🌭", "hotdog"],
        ["🥪", "sandwich"],
        ["🍜", "ramen noodles"],
        ["🍣", "sushi"],
        ["🍩", "donut doughnut"],
        ["🍪", "cookie biscuit"],
        ["🎂", "birthday cake"],
        ["🍰", "cake dessert"],
        ["🍫", "chocolate"],
        ["🍿", "popcorn"],
        ["☕", "coffee"],
        ["🧋", "bubble tea"],
        ["🍹", "tropical drink"],
        ["🍺", "beer"],
      ],
    },
    {
      id: "activities",
      icon: "⚽",
      items: [
        ["⚽", "soccer football"],
        ["🏀", "basketball"],
        ["🏈", "american football"],
        ["⚾", "baseball"],
        ["🎾", "tennis"],
        ["🏐", "volleyball"],
        ["🏉", "rugby"],
        ["🎱", "billiards pool"],
        ["🏓", "ping pong"],
        ["🏸", "badminton"],
        ["🥊", "boxing glove"],
        ["🥋", "martial arts"],
        ["🎮", "video game"],
        ["🕹️", "joystick arcade"],
        ["🎯", "dart target"],
        ["🎲", "dice game"],
        ["♟️", "chess"],
        ["🎸", "guitar music"],
        ["🎤", "microphone sing"],
        ["🎧", "headphones audio"],
        ["🎬", "movie clapper"],
        ["🎨", "art palette"],
        ["🛹", "skateboard"],
        ["🏆", "trophy win"],
      ],
    },
    {
      id: "travel",
      icon: "🌍",
      items: [
        ["🌍", "earth globe europe africa"],
        ["🌎", "earth globe americas"],
        ["🌏", "earth globe asia australia"],
        ["🗺️", "world map"],
        ["🧭", "compass"],
        ["🏕️", "camping tent"],
        ["🏝️", "island beach"],
        ["🏖️", "beach umbrella"],
        ["🏜️", "desert"],
        ["🏔️", "mountain snow"],
        ["🌋", "volcano"],
        ["🗽", "statue liberty"],
        ["🎡", "ferris wheel"],
        ["✈️", "airplane flight"],
        ["🚀", "rocket space"],
        ["🚗", "car"],
        ["🏎️", "race car"],
        ["🛵", "scooter"],
        ["🚲", "bicycle bike"],
        ["🛶", "canoe"],
        ["⛵", "sailboat boat"],
        ["🚄", "train bullet"],
        ["🚇", "metro subway"],
        ["🛸", "ufo"],
      ],
    },
    {
      id: "objects",
      icon: "💡",
      items: [
        ["💡", "light bulb idea"],
        ["🔦", "flashlight torch"],
        ["📱", "phone mobile"],
        ["💻", "laptop computer"],
        ["⌨️", "keyboard"],
        ["🖥️", "desktop monitor"],
        ["🖱️", "mouse computer"],
        ["📷", "camera photo"],
        ["📹", "video camera"],
        ["🎙️", "microphone studio"],
        ["📡", "satellite antenna"],
        ["🧲", "magnet"],
        ["⚙️", "gear settings"],
        ["🔧", "wrench tool"],
        ["🛠️", "tools build"],
        ["🔒", "lock secure"],
        ["🔑", "key"],
        ["💎", "gem diamond"],
        ["📦", "box package"],
        ["🎁", "gift present"],
        ["🧪", "test tube science"],
        ["💊", "pill"],
        ["🧬", "dna"],
        ["🪩", "mirror ball disco"],
      ],
    },
    {
      id: "symbols",
      icon: "🔣",
      items: [
        ["❤️", "red heart love"],
        ["🧡", "orange heart"],
        ["💛", "yellow heart"],
        ["💚", "green heart"],
        ["💙", "blue heart"],
        ["💜", "purple heart"],
        ["🖤", "black heart"],
        ["🤍", "white heart"],
        ["💯", "hundred score"],
        ["💢", "anger symbol"],
        ["💥", "boom explosion"],
        ["💫", "dizzy stars"],
        ["💤", "sleep symbol"],
        ["✨", "sparkles"],
        ["🔥", "fire lit"],
        ["⚡", "lightning fast"],
        ["✅", "check success"],
        ["❌", "cross fail"],
        ["❗", "exclamation"],
        ["❓", "question"],
        ["➕", "plus"],
        ["➖", "minus"],
        ["♻️", "recycle"],
        ["🔔", "bell notification"],
      ],
    },
    {
      id: "flags",
      icon: "🏁",
      items: [
        ["🏁", "checkered flag race"],
        ["🚩", "triangular flag"],
        ["🏳️", "white flag"],
        ["🏴", "black flag"],
        ["🇺🇸", "flag united states usa"],
        ["🇬🇧", "flag united kingdom uk britain"],
        ["🇲🇾", "flag malaysia"],
        ["🇸🇬", "flag singapore"],
        ["🇯🇵", "flag japan"],
        ["🇰🇷", "flag south korea"],
        ["🇨🇳", "flag china"],
        ["🇮🇳", "flag india"],
        ["🇦🇺", "flag australia"],
        ["🇳🇿", "flag new zealand"],
        ["🇨🇦", "flag canada"],
        ["🇫🇷", "flag france"],
        ["🇩🇪", "flag germany"],
        ["🇮🇹", "flag italy"],
        ["🇪🇸", "flag spain"],
        ["🇧🇷", "flag brazil"],
        ["🇦🇷", "flag argentina"],
        ["🇲🇽", "flag mexico"],
        ["🇿🇦", "flag south africa"],
        ["🇪🇺", "flag european union"],
      ],
    },
  ];

  const isMobile = () =>
    mobileQuery instanceof MediaQueryList
      ? mobileQuery.matches
      : window.innerWidth <= 768;

  const readJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { ok: false, error: text || "Request failed." };
    }
  };

  const showToast = (message, type = "success") => {
    if (!message) return;
    if (typeof window.truxToast === "function") {
      window.truxToast(message, type);
      return;
    }
    if (type === "error") {
      window.alert(message);
    }
  };

  const copyText = async (value) => {
    const text = String(value || "");
    if (!text) return;
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return;
    }
    const helper = document.createElement("textarea");
    helper.value = text;
    helper.setAttribute("readonly", "readonly");
    helper.style.position = "fixed";
    helper.style.top = "-9999px";
    document.body.appendChild(helper);
    helper.select();
    document.execCommand("copy");
    helper.remove();
  };

  const escapeHtml = (value) =>
    String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const textToHtml = (value) => escapeHtml(value).replace(/\n/g, "<br>");
  const joinHtmlFragments = (value) =>
    Array.isArray(value)
      ? value.map((fragment) => String(fragment || "")).join("")
      : String(value || "");
  const truncateText = (value, limit = 60) => {
    const text = String(value || "").replace(/\s+/g, " ").trim();
    if (text.length <= limit) {
      return text;
    }
    return `${text.slice(0, Math.max(1, limit - 1)).trim()}...`;
  };
  const normalizeEmojiText = (value) =>
    String(value || "")
      .toLowerCase()
      .normalize("NFKD");
  const buildEmojiCatalog = () =>
    EMOJI_CATEGORIES.map((category) => ({
      ...category,
      items: category.items.map(([emoji, keywords]) => ({
        emoji,
        keywords: String(keywords || ""),
      })),
    }));
  const emojiCatalog = buildEmojiCatalog();
  const emojiCategoryMap = new Map(
    emojiCatalog.map((category) => [category.id, category])
  );

  const canUseDesktopEnterToSend = () => {
    const finePointer =
      finePointerQuery instanceof MediaQueryList ? finePointerQuery.matches : true;
    return finePointer && !isMobile();
  };

  const isPlainPrimaryClick = (event) =>
    event.button === 0 &&
    !event.defaultPrevented &&
    !event.metaKey &&
    !event.ctrlKey &&
    !event.shiftKey &&
    !event.altKey;

  const toMessagesUrl = (value) => {
    try {
      const nextUrl = new URL(
        String(value || window.location.href),
        window.location.origin
      );
      return /\/messages\.php$/i.test(nextUrl.pathname) ? nextUrl : null;
    } catch {
      return null;
    }
  };

  const toThreadPartialUrl = (value) => {
    const nextUrl = toMessagesUrl(value);
    if (!(nextUrl instanceof URL)) return null;
    nextUrl.searchParams.set("partial", "thread");
    return nextUrl;
  };

  const extractThreadErrorMessage = (html, fallbackMessage) => {
    const markup = String(html || "").trim();
    if (!markup) {
      return fallbackMessage;
    }

    try {
      const doc = new DOMParser().parseFromString(markup, "text/html");
      const errorNode = doc.querySelector("[data-messages-thread-error='1']");
      const text = String(errorNode?.textContent || doc.body?.textContent || "").trim();
      return text || fallbackMessage;
    } catch {
      return fallbackMessage;
    }
  };

  const urlHasThreadState = (value) => {
    const nextUrl = toMessagesUrl(value);
    return !!(
      nextUrl instanceof URL &&
      (nextUrl.searchParams.has("id") || nextUrl.searchParams.has("with"))
    );
  };

  const getConversationItems = () =>
    Array.from(layout.querySelectorAll("[data-conversation-item='1']")).filter(
      (item) => item instanceof HTMLAnchorElement
    );
  const getConversationList = () => {
    const list = layout.querySelector("[data-conversation-list='1']");
    return list instanceof HTMLElement ? list : null;
  };
  const getConversationListEmptyState = () => {
    const node = layout.querySelector("[data-conversation-list-empty='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getThreadPanel = () => {
    const panel = layout.querySelector("[data-messages-thread='1']");
    return panel instanceof HTMLElement ? panel : null;
  };
  const getMobileThreadState = () => {
    const node = layout.querySelector(".messagesMobileBar__state--thread");
    return node instanceof HTMLElement ? node : null;
  };
  const getComposerForm = () => {
    const form = layout.querySelector("[data-messages-composer='1']");
    return form instanceof HTMLFormElement ? form : null;
  };
  const getComposerInput = () => {
    const input = layout.querySelector("[data-messages-input='1']");
    return input instanceof HTMLTextAreaElement ? input : null;
  };
  const getComposerSubmit = (form = getComposerForm()) => {
    if (!(form instanceof HTMLFormElement)) return null;
    const button = form.querySelector("[data-messages-submit='1']");
    return button instanceof HTMLButtonElement ? button : null;
  };
  const getAttachmentTrigger = () => {
    const button = layout.querySelector("[data-messages-attachment-trigger='1']");
    return button instanceof HTMLButtonElement ? button : null;
  };
  const getAttachmentInput = () => {
    const input = layout.querySelector("[data-messages-attachment-input='1']");
    return input instanceof HTMLInputElement ? input : null;
  };
  const getImageAttachmentInput = () => {
    const input = layout.querySelector("[data-messages-image-input='1']");
    return input instanceof HTMLInputElement ? input : null;
  };
  const getAttachmentMenu = () => {
    const menu = layout.querySelector("[data-messages-attachment-menu='1']");
    return menu instanceof HTMLElement ? menu : null;
  };
  const getAttachmentDropdown = () => {
    const dropdown = layout.querySelector("[data-messages-attachment-dropdown='1']");
    return dropdown instanceof HTMLElement ? dropdown : null;
  };
  const getAttachmentPreview = () => {
    const preview = layout.querySelector("[data-messages-attachment-preview='1']");
    return preview instanceof HTMLElement ? preview : null;
  };
  const getComposerReplyInput = () => {
    const input = layout.querySelector("[data-messages-reply-input='1']");
    return input instanceof HTMLInputElement ? input : null;
  };
  const getComposerReplyBar = () => {
    const bar = layout.querySelector("[data-messages-reply-context='1']");
    return bar instanceof HTMLElement ? bar : null;
  };
  const getComposerReplyTitle = () => {
    const node = layout.querySelector("[data-messages-reply-title='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getComposerReplyPreview = () => {
    const node = layout.querySelector("[data-messages-reply-preview='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getComposerEmojiPanel = () => {
    const panel = layout.querySelector("[data-messages-emoji-panel='1']");
    return panel instanceof HTMLElement ? panel : null;
  };
  const getComposerEmojiSearch = () => {
    const input = layout.querySelector("[data-messages-emoji-search='1']");
    return input instanceof HTMLInputElement ? input : null;
  };
  const getComposerEmojiRecent = () => {
    const node = layout.querySelector("[data-messages-emoji-recent='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getComposerEmojiTabs = () => {
    const node = layout.querySelector("[data-messages-emoji-tabs='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getComposerEmojiGrid = () => {
    const node = layout.querySelector("[data-messages-emoji-grid='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getMessageList = () => {
    const list = layout.querySelector("[data-message-list='1']");
    return list instanceof HTMLElement ? list : null;
  };
  const getMessageEmptyState = () => {
    const node = layout.querySelector("[data-message-empty-state='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getLoadOlderRow = () => {
    const node = layout.querySelector("[data-message-load-row='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getLoadOlderButton = () => {
    const button = layout.querySelector("[data-message-load-older='1']");
    return button instanceof HTMLButtonElement ? button : null;
  };
  const getJumpButton = () => {
    const button = layout.querySelector("[data-message-jump-latest='1']");
    return button instanceof HTMLButtonElement ? button : null;
  };
  const getJumpLabel = () => {
    const label = layout.querySelector("[data-message-jump-label='1']");
    return label instanceof HTMLElement ? label : null;
  };
  const getThreadTransportState = () => {
    const node = layout.querySelector("[data-message-transport-state='1']");
    return node instanceof HTMLElement ? node : null;
  };
  const getThreadActionsSheet = () => {
    const sheet = document.querySelector("[data-shell-sheet='message-actions']");
    return sheet instanceof HTMLElement ? sheet : null;
  };
  const getBubbleActionsSheet = () => {
    const sheet = document.querySelector("[data-shell-sheet='message-bubble-actions']");
    return sheet instanceof HTMLElement ? sheet : null;
  };
  const getActiveConversationId = () =>
    Number(layout.getAttribute("data-messages-active-conversation-id") || "0");
  const getInboxUrl = () => {
    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.delete("id");
    nextUrl.searchParams.delete("with");
    nextUrl.hash = "";
    return nextUrl.toString();
  };
  const getCsrfToken = () => {
    const input = document.querySelector("input[name='_csrf']");
    return input instanceof HTMLInputElement
      ? String(input.value || "").trim()
      : "";
  };

  const createNodeFromHtml = (html, selector) => {
    const markup = joinHtmlFragments(html).trim();
    if (!markup) return null;
    const template = document.createElement("template");
    template.innerHTML = markup;
    if (!selector) {
      const firstNode = template.content.firstElementChild;
      return firstNode instanceof HTMLElement ? firstNode : null;
    }
    const match = template.content.querySelector(selector);
    return match instanceof HTMLElement ? match : null;
  };

  const createNodesFromHtml = (html, selector) => {
    const markup = joinHtmlFragments(html).trim();
    if (!markup) return [];
    const template = document.createElement("template");
    template.innerHTML = markup;
    return Array.from(template.content.querySelectorAll(selector)).filter(
      (node) => node instanceof HTMLElement
    );
  };

  const removeConversationListEmptyState = () => {
    const node = getConversationListEmptyState();
    if (node instanceof HTMLElement) node.remove();
  };

  const removeMessageEmptyState = () => {
    const node = getMessageEmptyState();
    if (node instanceof HTMLElement) node.remove();
  };

  const openSheet = (name) => {
    const sheet = document.querySelector(
      `[data-shell-sheet="${String(name || "")}"]`
    );
    if (!(sheet instanceof HTMLElement)) return null;
    sheet.removeAttribute("hidden");
    document.body.classList.add("shellSheet-open");
    return sheet;
  };

  const closeSheet = (name) => {
    const sheet =
      name instanceof HTMLElement
        ? name
        : document.querySelector(`[data-shell-sheet="${String(name || "")}"]`);
    if (!(sheet instanceof HTMLElement)) return;
    sheet.setAttribute("hidden", "hidden");
    const openSheetExists = document.querySelector(
      "[data-shell-sheet]:not([hidden])"
    );
    if (!(openSheetExists instanceof HTMLElement)) {
      document.body.classList.remove("shellSheet-open");
    }
  };

  const closeRecipientSheet = () => {
    closeSheet("message-recipient");
  };

  const updateGlobalMessageUnread = (count) => {
    const unread = Math.max(0, Number(count || 0));
    const label = unread > 99 ? "99+" : String(unread);
    document.querySelectorAll("[data-message-unread-badge]").forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      node.hidden = unread <= 0;
      node.textContent = unread <= 0 ? "" : label;
    });
  };

  const setThreadOpen = (open) => {
    layout.classList.toggle("is-thread-open", open);
    body.classList.toggle("messages-thread-active", open);
  };

  const setThreadBusy = (busy) => {
    const threadPanel = getThreadPanel();
    if (threadPanel instanceof HTMLElement) {
      threadPanel.setAttribute("aria-busy", busy ? "true" : "false");
    }
    layout.classList.toggle("is-thread-loading", busy);
  };

  const updateThreadStatusCopy = (label) => {
    if (!label) return;
    layout.querySelectorAll("[data-thread-status-copy='1']").forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      node.textContent = label;
    });
  };

  const isComposerBusy = (form = getComposerForm()) =>
    form instanceof HTMLFormElement && form.dataset.sending === "1";

  const setComposerBusy = (form, busy) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.dataset.sending = busy ? "1" : "0";
    form.classList.toggle("is-sending", busy);

    const submitButton = getComposerSubmit(form);
    const attachmentTrigger = getAttachmentTrigger();
    const attachmentInput = getAttachmentInput();
    const imageInput = getImageAttachmentInput();
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = busy;
      submitButton.classList.toggle("is-loading", busy);
      submitButton.setAttribute("aria-disabled", busy ? "true" : "false");
    }
    if (attachmentTrigger instanceof HTMLButtonElement) {
      attachmentTrigger.disabled = busy;
      attachmentTrigger.setAttribute("aria-disabled", busy ? "true" : "false");
    }
    if (attachmentInput instanceof HTMLInputElement) {
      attachmentInput.disabled = busy;
    }
    if (imageInput instanceof HTMLInputElement) {
      imageInput.disabled = busy;
    }
    form
      .querySelectorAll("[data-messages-attachment-action]")
      .forEach((button) => {
        if (button instanceof HTMLButtonElement) {
          button.disabled = busy;
        }
      });
  };

  const setAttachmentMenuOpen = (open) => {
    const menu = getAttachmentMenu();
    const trigger = getAttachmentTrigger();
    const dropdown = getAttachmentDropdown();
    if (!(menu instanceof HTMLElement) || !(trigger instanceof HTMLButtonElement)) {
      return;
    }

    const nextOpen = !!open && !trigger.disabled;
    menu.classList.toggle("is-open", nextOpen);
    trigger.setAttribute("aria-expanded", nextOpen ? "true" : "false");
    if (nextOpen) {
      closeEmojiPanel();
    }
    if (dropdown instanceof HTMLElement) {
      dropdown.hidden = !nextOpen;
    }
  };

  const closeAttachmentMenu = () => {
    setAttachmentMenuOpen(false);
  };

  const toggleAttachmentMenu = () => {
    const menu = getAttachmentMenu();
    if (!(menu instanceof HTMLElement)) return;
    setAttachmentMenuOpen(!menu.classList.contains("is-open"));
  };

  const setComposerConversationId = (conversationId) => {
    const form = getComposerForm();
    if (!(form instanceof HTMLFormElement) || !conversationId) return;

    form.querySelectorAll("input[name='recipient_id']").forEach((node) =>
      node.remove()
    );

    let conversationInput = form.querySelector("input[name='conversation_id']");
    if (!(conversationInput instanceof HTMLInputElement)) {
      conversationInput = document.createElement("input");
      conversationInput.type = "hidden";
      conversationInput.name = "conversation_id";

      const csrfInput = form.querySelector("input[name='_csrf']");
      if (csrfInput instanceof HTMLInputElement && csrfInput.parentNode === form) {
        csrfInput.insertAdjacentElement("afterend", conversationInput);
      } else {
        form.insertBefore(conversationInput, form.firstChild);
      }
    }

    conversationInput.value = String(conversationId);
  };

  const setThreadTransportMarkup = (markup, options = {}) => {
    const node = getThreadTransportState();
    if (!(node instanceof HTMLElement)) return;
    const html = String(markup || "").trim();
    node.innerHTML = html;
    node.hidden = html === "";
    node.classList.toggle("is-loading", options.loading === true);
    node.classList.toggle("is-error", options.error === true);
  };

  const clearThreadTransportState = () => {
    state.threadTransportRetryUrl = "";
    setThreadTransportMarkup("");
  };

  const showThreadTransportLoading = () => {
    setThreadTransportMarkup(
      `
        <div class="messagesThread__transportCard messagesThread__transportCard--loading">
          <span class="messagesThread__transportSpinner" aria-hidden="true"></span>
          <span>Loading conversation...</span>
        </div>
      `,
      { loading: true }
    );
  };

  const showThreadTransportError = (message, retryUrl) => {
    state.threadTransportRetryUrl = String(retryUrl || "");
    setThreadTransportMarkup(
      `
        <div class="messagesThread__transportCard messagesThread__transportCard--error">
          <strong>Failed to load conversation.</strong>
          <span>${escapeHtml(String(message || "Retry?"))}</span>
          <button class="shellButton shellButton--ghost" type="button" data-thread-transport-retry="1">Retry</button>
        </div>
      `,
      { error: true }
    );
  };

  const loadRecentEmoji = () => {
    try {
      const raw = window.localStorage.getItem(EMOJI_RECENT_STORAGE_KEY);
      const parsed = JSON.parse(raw || "[]");
      return Array.isArray(parsed)
        ? parsed.filter((item) => typeof item === "string" && item !== "").slice(0, 8)
        : [];
    } catch {
      return [];
    }
  };

  const saveRecentEmoji = (emoji) => {
    const nextEmoji = String(emoji || "").trim();
    if (!nextEmoji) return;
    const nextList = [nextEmoji]
      .concat(loadRecentEmoji().filter((item) => item !== nextEmoji))
      .slice(0, 8);
    try {
      window.localStorage.setItem(EMOJI_RECENT_STORAGE_KEY, JSON.stringify(nextList));
    } catch {
      // Ignore storage failures and keep the picker usable.
    }
  };

  const getFilteredEmojiItems = () => {
    const query = normalizeEmojiText(getComposerEmojiSearch()?.value || "");
    const categoryId = state.emojiCategory || emojiCatalog[0]?.id || "smileys";

    const filterItems = (items) =>
      items.filter((item) => {
        if (!query) return true;
        return normalizeEmojiText(`${item.emoji} ${item.keywords}`).includes(query);
      });

    const recentItems = loadRecentEmoji()
      .map((emoji) => {
        const match = emojiCatalog
          .flatMap((category) => category.items)
          .find((item) => item.emoji === emoji);
        return match || null;
      })
      .filter(Boolean);

    const activeCategory = emojiCategoryMap.get(categoryId) || emojiCatalog[0];
    return {
      recent: filterItems(recentItems),
      category: activeCategory,
      items: filterItems(activeCategory?.items || []),
    };
  };

  const renderEmojiPicker = () => {
    const panel = getComposerEmojiPanel();
    const recentRow = getComposerEmojiRecent();
    const tabs = getComposerEmojiTabs();
    const grid = getComposerEmojiGrid();
    if (
      !(panel instanceof HTMLElement) ||
      !(tabs instanceof HTMLElement) ||
      !(grid instanceof HTMLElement)
    ) {
      return;
    }

    const { recent, category, items } = getFilteredEmojiItems();

    tabs.innerHTML = emojiCatalog
      .map(
        (entry) => `
          <button
            class="messagesComposer__emojiTab${entry.id === category?.id ? " is-active" : ""}"
            type="button"
            data-messages-emoji-tab="${escapeHtml(entry.id)}"
            aria-label="${escapeHtml(entry.id)}"
            aria-pressed="${entry.id === category?.id ? "true" : "false"}">
            <span aria-hidden="true">${entry.icon}</span>
          </button>
        `
      )
      .join("");

    if (recentRow instanceof HTMLElement) {
      recentRow.hidden = recent.length < 1;
      recentRow.innerHTML = recent.length
        ? `
            <span class="messagesComposer__emojiLabel">Recent</span>
            <div class="messagesComposer__emojiRecentGrid">
              ${recent
                .map(
                  (item) => `
                    <button
                      class="messagesComposer__emojiButton"
                      type="button"
                      data-messages-emoji-value="${escapeHtml(item.emoji)}"
                      aria-label="${escapeHtml(item.keywords)}">${item.emoji}</button>
                  `
                )
                .join("")}
            </div>
          `
        : "";
    }

    grid.innerHTML = items.length
      ? items
          .map(
            (item) => `
              <button
                class="messagesComposer__emojiButton"
                type="button"
                data-messages-emoji-value="${escapeHtml(item.emoji)}"
                aria-label="${escapeHtml(item.keywords)}">${item.emoji}</button>
            `
          )
          .join("")
      : `<div class="messagesComposer__emojiEmpty muted">No emoji found.</div>`;

    panel.dataset.emojiCategory = category?.id || "";
  };

  const closeEmojiPanel = () => {
    const panel = getComposerEmojiPanel();
    if (!(panel instanceof HTMLElement)) return;
    panel.hidden = true;
    panel.classList.remove("is-open");
  };

  const openEmojiPanel = () => {
    const panel = getComposerEmojiPanel();
    if (!(panel instanceof HTMLElement)) return;
    closeAttachmentMenu();
    renderEmojiPicker();
    panel.hidden = false;
    panel.classList.add("is-open");
    const search = getComposerEmojiSearch();
    if (search instanceof HTMLInputElement) {
      window.setTimeout(() => {
        search.focus();
        search.select();
      }, 20);
    }
  };

  const setComposerReplyContext = (replyContext) => {
    const context =
      replyContext &&
      Number(replyContext.messageId || 0) > 0 &&
      String(replyContext.preview || "").trim() !== ""
        ? {
            messageId: Number(replyContext.messageId || 0),
            senderUsername: String(replyContext.senderUsername || "").trim(),
            preview: truncateText(replyContext.preview, 60),
          }
        : null;
    state.replyContext = context;

    const bar = getComposerReplyBar();
    const input = getComposerReplyInput();
    const title = getComposerReplyTitle();
    const preview = getComposerReplyPreview();

    if (input instanceof HTMLInputElement) {
      input.value = context ? String(context.messageId) : "";
    }
    if (bar instanceof HTMLElement) {
      bar.hidden = !context;
    }
    if (title instanceof HTMLElement) {
      title.textContent = context
        ? context.senderUsername
          ? `Replying to @${context.senderUsername}`
          : "Replying to deleted message"
        : "Replying";
    }
    if (preview instanceof HTMLElement) {
      preview.textContent = context ? context.preview : "";
    }
  };

  const clearComposerReplyContext = () => {
    setComposerReplyContext(null);
  };

  const syncComposerHeight = (input = getComposerInput()) => {
    if (!(input instanceof HTMLTextAreaElement)) return;

    if (typeof window.truxAutosizeTextarea === "function") {
      window.truxAutosizeTextarea(input);
      return;
    }

    input.style.height = "auto";
    const computed = window.getComputedStyle(input);
    const minHeight = Number.parseFloat(computed.minHeight || "0");
    const maxHeight = Number.parseFloat(computed.maxHeight || "0");
    let nextHeight = input.scrollHeight;

    if (Number.isFinite(minHeight) && minHeight > 0) {
      nextHeight = Math.max(minHeight, nextHeight);
    }
    if (Number.isFinite(maxHeight) && maxHeight > 0) {
      nextHeight = Math.min(maxHeight, nextHeight);
    }

    if (nextHeight > 0) {
      input.style.height = `${nextHeight}px`;
    }
    input.style.overflowY =
      Number.isFinite(maxHeight) && maxHeight > 0 && input.scrollHeight > maxHeight
        ? "auto"
        : "hidden";
  };

  const updateConversationFilter = () => {
    if (!(searchInput instanceof HTMLInputElement)) return;

    const query = searchInput.value.trim().toLowerCase();
    const conversationItems = getConversationItems();
    let visibleCount = 0;

    conversationItems.forEach((item) => {
      let searchText = String(item.dataset.searchTextCache || "");
      if (searchText === "") {
        searchText = String(item.getAttribute("data-search-text") || "").toLowerCase();
        if (searchText !== "") {
          item.dataset.searchTextCache = searchText;
        }
      }
      const isMatch = query === "" || searchText.includes(query);
      item.hidden = !isMatch;
      if (isMatch) {
        visibleCount += 1;
      }
    });

    if (emptyState instanceof HTMLElement) {
      emptyState.hidden =
        query === "" || visibleCount > 0 || conversationItems.length === 0;
    }
  };

  const scheduleConversationFilter = () => {
    if (state.conversationFilterFrame !== 0) return;
    state.conversationFilterFrame = window.requestAnimationFrame(() => {
      state.conversationFilterFrame = 0;
      updateConversationFilter();
    });
  };

  const getBubbleId = (bubble) =>
    bubble instanceof HTMLElement
      ? String(bubble.getAttribute("data-message-id") || "").trim()
      : "";

  const findMessageBubble = (messageId) => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return null;
    const bubble = list.querySelector(
      `[data-message-bubble='1'][data-message-id='${String(messageId || "")}']`
    );
    return bubble instanceof HTMLElement ? bubble : null;
  };

  const getDomMessageBubbles = () => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return [];
    return Array.from(list.querySelectorAll("[data-message-bubble='1']")).filter(
      (bubble) => bubble instanceof HTMLElement
    );
  };

  const getQuickActionsBar = (bubble) => {
    if (!(bubble instanceof HTMLElement)) return null;
    const bar = bubble.querySelector("[data-message-hover-actions='1']");
    return bar instanceof HTMLElement ? bar : null;
  };

  const rectsIntersect = (a, b) =>
    !!a &&
    !!b &&
    a.left < b.right &&
    a.right > b.left &&
    a.top < b.bottom &&
    a.bottom > b.top;

  const positionQuickActions = (bubble, options = {}) => {
    if (!(bubble instanceof HTMLElement)) return;
    const bar = getQuickActionsBar(bubble);
    const viewport = getMessageList() || getThreadPanel();
    if (!(bar instanceof HTMLElement) || !(viewport instanceof HTMLElement)) {
      return;
    }

    const isMine = bubble.classList.contains("messageBubble--mine");
    const gap = 8;
    const threadRect = viewport.getBoundingClientRect();
    const bubbleRect = bubble.getBoundingClientRect();
    const barWidth = bar.offsetWidth || 44;
    const barHeight = bar.offsetHeight || 128;
    const menu = bubble.querySelector("[data-dm-message-menu='1']");
    const menuTrigger = menu?.querySelector("[data-content-menu-trigger='1']");
    const menuPanel = menu?.querySelector(".contentMenu__panel");
    const menuRects = [];

    if (menuTrigger instanceof HTMLElement) {
      menuRects.push(menuTrigger.getBoundingClientRect());
    }
    if (menuPanel instanceof HTMLElement && menu?.classList.contains("is-open")) {
      menuRects.push(menuPanel.getBoundingClientRect());
    }

    if (options.mobile) {
      const centeredLeft = bubbleRect.left + bubbleRect.width / 2 - barWidth / 2;
      const minLeft = threadRect.left + 4;
      const maxLeft = threadRect.right - barWidth - 4;
      const clampedLeft = Math.max(minLeft, Math.min(maxLeft, centeredLeft));
      const centerLeft = bubbleRect.left + bubbleRect.width / 2 - barWidth / 2;
      const shiftX = clampedLeft - centerLeft;

      bubble.dataset.quickActionsPlacement = "above";
      bar.style.setProperty("--quick-actions-shift-x", `${shiftX}px`);
      return;
    }

    const getPlacementRect = (placement) => {
      if (placement === "left") {
        return {
          left: bubbleRect.left - gap - barWidth,
          right: bubbleRect.left - gap,
          top: bubbleRect.top + bubbleRect.height / 2 - barHeight / 2,
          bottom: bubbleRect.top + bubbleRect.height / 2 + barHeight / 2,
        };
      }

      return {
        left: bubbleRect.right + gap,
        right: bubbleRect.right + gap + barWidth,
        top: bubbleRect.top + bubbleRect.height / 2 - barHeight / 2,
        bottom: bubbleRect.top + bubbleRect.height / 2 + barHeight / 2,
      };
    };

    const placementOverflows = (placement) => {
      const rect = getPlacementRect(placement);
      if (rect.left < threadRect.left + 4 || rect.right > threadRect.right - 4) {
        return true;
      }

      return menuRects.some((menuRect) => rectsIntersect(rect, menuRect));
    };

    let placement = isMine ? "left" : "right";
    if (placementOverflows(placement)) {
      placement = placement === "left" ? "right" : "left";
    }

    bubble.dataset.quickActionsPlacement = placement;
    bar.style.removeProperty("--quick-actions-shift-x");
  };

  const clearQuickActionsHideTimer = () => {
    window.clearTimeout(state.quickActionsHideTimer);
    state.quickActionsHideTimer = 0;
  };

  const closeQuickActions = (bubble = null) => {
    clearQuickActionsHideTimer();
    const targetBubble =
      bubble instanceof HTMLElement
        ? bubble
        : findMessageBubble(state.quickActionsBubbleId);
    if (targetBubble instanceof HTMLElement) {
      targetBubble.classList.remove("is-quick-actions-open");
      hideDeleteConfirmation(targetBubble);
    }
    state.quickActionsBubbleId = "";
    state.quickActionsMode = "";
  };

  const openQuickActions = (bubble, options = {}) => {
    if (!(bubble instanceof HTMLElement)) return;
    const bar = getQuickActionsBar(bubble);
    if (!(bar instanceof HTMLElement)) return;

    clearQuickActionsHideTimer();

    if (state.quickActionsBubbleId && state.quickActionsBubbleId !== getBubbleId(bubble)) {
      closeQuickActions(findMessageBubble(state.quickActionsBubbleId));
    }

    state.quickActionsBubbleId = getBubbleId(bubble);
    state.quickActionsMode = options.mobile ? "mobile" : "hover";
    positionQuickActions(bubble, options);
    bubble.classList.add("is-quick-actions-open");
  };

  const scheduleQuickActionsClose = (bubble, delay = 220) => {
    if (!(bubble instanceof HTMLElement) || state.quickActionsMode === "mobile") {
      return;
    }
    clearQuickActionsHideTimer();
    state.quickActionsHideTimer = window.setTimeout(() => {
      if (!bubble.classList.contains("is-delete-confirm-open")) {
        closeQuickActions(bubble);
      }
    }, delay);
  };

  const formatMessageDayKey = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const formatMessageDayLabel = (date) => {
    const today = new Date();
    const todayKey = formatMessageDayKey(today);
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    const messageKey = formatMessageDayKey(date);
    if (messageKey === todayKey) {
      return "Today";
    }
    if (messageKey === formatMessageDayKey(yesterday)) {
      return "Yesterday";
    }

    return date.toLocaleDateString(undefined, {
      month: "short",
      day: "numeric",
      year: today.getFullYear() !== date.getFullYear() ? "numeric" : undefined,
    });
  };

  const createDateSeparator = (dayKey, dayLabel) => {
    const separator = document.createElement("div");
    separator.className = "messagesThread__daySeparator";
    separator.setAttribute("data-message-day-separator", "1");
    separator.setAttribute("data-message-day-key", dayKey);
    separator.innerHTML = `<span>${escapeHtml(dayLabel || dayKey)}</span>`;
    return separator;
  };

  const rebuildDateSeparators = () => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return;

    list
      .querySelectorAll("[data-message-day-separator='1']")
      .forEach((node) => node.remove());

    let lastDayKey = "";
    getDomMessageBubbles().forEach((bubble) => {
      const dayKey = String(bubble.dataset.messageDayKey || "").trim();
      const dayLabel = String(bubble.dataset.messageDayLabel || "").trim();
      if (!dayKey || dayKey === lastDayKey) return;
      lastDayKey = dayKey;
      bubble.before(createDateSeparator(dayKey, dayLabel || dayKey));
    });
  };

  const refreshBubbleGrouping = () => {
    const bubbles = getDomMessageBubbles();
    bubbles.forEach((bubble) => {
      bubble.classList.remove(
        "messageBubble--groupedPrev",
        "messageBubble--groupedNext"
      );
    });

    bubbles.forEach((bubble, index) => {
      const prev = bubbles[index - 1] || null;
      const next = bubbles[index + 1] || null;
      const lane = bubble.classList.contains("messageBubble--mine") ? "mine" : "other";
      const sameDay = (candidate) =>
        candidate instanceof HTMLElement &&
        String(candidate.dataset.messageDayKey || "") ===
          String(bubble.dataset.messageDayKey || "");
      const sameLane = (candidate) =>
        candidate instanceof HTMLElement &&
        (candidate.classList.contains("messageBubble--mine") ? "mine" : "other") === lane;

      bubble.classList.toggle("messageBubble--groupedPrev", sameDay(prev) && sameLane(prev));
      bubble.classList.toggle("messageBubble--groupedNext", sameDay(next) && sameLane(next));
    });
  };

  const updateThreadCursorData = () => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return;
    list.dataset.latestMessageId = String(state.latestMessageId || 0);
    list.dataset.oldestMessageId = String(state.oldestMessageId || 0);
    list.dataset.hasMoreBefore = state.hasMoreBefore ? "1" : "0";

    const loadRow = getLoadOlderRow();
    if (loadRow instanceof HTMLElement) {
      loadRow.hidden = !state.hasMoreBefore;
    }
  };

  const updateJumpAffordance = () => {
    const button = getJumpButton();
    const label = getJumpLabel();
    if (!(button instanceof HTMLButtonElement) || !(label instanceof HTMLElement)) {
      return;
    }

    if (state.jumpCount <= 0) {
      button.hidden = true;
      label.textContent = "Jump to latest";
      return;
    }

    label.textContent =
      state.jumpCount === 1
        ? "1 new message"
        : `${state.jumpCount} new messages`;
    button.hidden = false;
  };

  const resetJumpAffordance = () => {
    state.jumpCount = 0;
    updateJumpAffordance();
  };

  const bumpJumpAffordance = (count = 1) => {
    state.jumpCount += Math.max(1, count);
    updateJumpAffordance();
  };

  const syncThreadStateFromDom = () => {
    state.knownMessageIds = new Set();
    state.latestMessageId = 0;
    state.oldestMessageId = 0;

    getDomMessageBubbles().forEach((bubble) => {
      const bubbleId = getBubbleId(bubble);
      if (bubbleId !== "") {
        state.knownMessageIds.add(bubbleId);
      }

      const numericId = Number(bubbleId);
      if (!Number.isFinite(numericId) || numericId <= 0) {
        return;
      }

      if (!state.latestMessageId || numericId > state.latestMessageId) {
        state.latestMessageId = numericId;
      }
      if (!state.oldestMessageId || numericId < state.oldestMessageId) {
        state.oldestMessageId = numericId;
      }
    });

    const list = getMessageList();
    state.hasMoreBefore = list instanceof HTMLElement && list.dataset.hasMoreBefore === "1";
    updateThreadCursorData();
    rebuildDateSeparators();
    refreshBubbleGrouping();
  };

  const isNearBottom = (list = getMessageList(), threshold = 96) => {
    if (!(list instanceof HTMLElement)) return true;
    return list.scrollHeight - (list.scrollTop + list.clientHeight) <= threshold;
  };

  const scrollMessageListToLatest = (behavior = "smooth") => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return;
    list.scrollTo({
      top: list.scrollHeight,
      behavior,
    });
    resetJumpAffordance();
  };

  const setActiveConversationState = (conversationId) => {
    layout.setAttribute("data-messages-active-conversation-id", String(conversationId || 0));
    layout.classList.toggle("is-thread-active", conversationId > 0);

    const messageList = getMessageList();
    if (messageList instanceof HTMLElement) {
      messageList.dataset.conversationId = String(conversationId || 0);
    }

    getConversationItems().forEach((item) => {
      const itemUrl = toMessagesUrl(item.href);
      const itemConversationId =
        itemUrl instanceof URL ? Number(itemUrl.searchParams.get("id") || "0") : 0;
      const isActive = conversationId > 0 && itemConversationId === conversationId;
      const unreadDot = item.querySelector(".messagesList__unread");

      item.classList.toggle("is-active", isActive);
      if (isActive) {
        item.setAttribute("aria-current", "page");
        item.dataset.unreadCount = "0";
        if (unreadDot instanceof HTMLElement) {
          unreadDot.classList.remove("is-visible");
        }
      } else {
        item.removeAttribute("aria-current");
      }
    });
  };

  const upsertConversationItem = (html, conversationId, options = {}) => {
    const list = getConversationList();
    const nextItem = createNodeFromHtml(html, "[data-conversation-item='1']");
    if (!(list instanceof HTMLElement) || !(nextItem instanceof HTMLAnchorElement)) {
      return false;
    }

    removeConversationListEmptyState();

    const existingItem = list.querySelector(
      `[data-conversation-item='1'][data-conversation-id='${conversationId}']`
    );
    if (existingItem instanceof HTMLElement) {
      existingItem.replaceWith(nextItem);
    } else {
      const firstConversationItem = getConversationItems()[0];
      const searchEmpty = layout.querySelector("[data-conversation-empty='1']");
      if (firstConversationItem instanceof HTMLAnchorElement) {
        list.insertBefore(nextItem, firstConversationItem);
      } else if (searchEmpty instanceof HTMLElement) {
        list.insertBefore(nextItem, searchEmpty);
      } else {
        list.appendChild(nextItem);
      }
    }

    if (options.moveToTop !== false) {
      const liveItem = list.querySelector(
        `[data-conversation-item='1'][data-conversation-id='${conversationId}']`
      );
      const firstConversationItem = getConversationItems()[0];
      if (
        liveItem instanceof HTMLElement &&
        firstConversationItem instanceof HTMLAnchorElement &&
        liveItem !== firstConversationItem
      ) {
        list.insertBefore(liveItem, firstConversationItem);
      }
    }

    setActiveConversationState(getActiveConversationId());
    updateConversationFilter();
    return true;
  };

  const applyConversationPayload = (data, options = {}) => {
    const conversationId =
      Number(data?.conversation_id || data?.conversation_summary?.id || 0) ||
      getActiveConversationId();
    const conversationHtml = joinHtmlFragments(
      data?.conversation_item_html || data?.conversation_summary_html || ""
    );

    if (conversationId > 0 && conversationHtml) {
      upsertConversationItem(conversationHtml, conversationId, options);
    }

    if (Number.isFinite(Number(data?.unread_total))) {
      updateGlobalMessageUnread(Number(data.unread_total || 0));
    }

    if (conversationId > 0 && conversationId === getActiveConversationId()) {
      setActiveConversationState(conversationId);
    }
  };

  const insertMessageElements = (elements, options = {}) => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement) || !Array.isArray(elements) || !elements.length) {
      return 0;
    }

    const shouldStick = options.forceScroll === true || isNearBottom(list);
    const fragment = document.createDocumentFragment();
    let added = 0;

    elements.forEach((bubble) => {
      if (!(bubble instanceof HTMLElement)) return;
      const bubbleId = getBubbleId(bubble);
      if (bubbleId !== "" && state.knownMessageIds.has(bubbleId)) {
        return;
      }
      fragment.appendChild(bubble);
      added += 1;
    });

    if (added < 1) {
      return 0;
    }

    removeMessageEmptyState();
    list.appendChild(fragment);
    syncThreadStateFromDom();

    if (shouldStick) {
      scrollMessageListToLatest(options.scrollBehavior || "smooth");
    } else if (options.bumpJump !== false) {
      bumpJumpAffordance(added);
    }

    if (typeof window.truxRefreshTimeAgo === "function") {
      window.truxRefreshTimeAgo();
    }

    return added;
  };

  const appendMessagesHtml = (html, options = {}) =>
    insertMessageElements(
      createNodesFromHtml(html, "[data-message-bubble='1']"),
      options
    );

  const prependMessagesHtml = (html) => {
    const list = getMessageList();
    if (!(list instanceof HTMLElement)) return 0;

    const elements = createNodesFromHtml(html, "[data-message-bubble='1']");
    if (!elements.length) return 0;

    const beforeHeight = list.scrollHeight;
    const firstBubble = list.querySelector("[data-message-bubble='1']");
    const emptyStateNode = getMessageEmptyState();
    const anchor =
      firstBubble instanceof HTMLElement
        ? firstBubble
        : emptyStateNode instanceof HTMLElement
          ? emptyStateNode
          : null;

    let added = 0;
    elements.forEach((bubble) => {
      if (!(bubble instanceof HTMLElement)) return;
      const bubbleId = getBubbleId(bubble);
      if (bubbleId !== "" && state.knownMessageIds.has(bubbleId)) {
        return;
      }
      removeMessageEmptyState();
      if (anchor instanceof HTMLElement) {
        list.insertBefore(bubble, anchor);
      } else {
        list.appendChild(bubble);
      }
      added += 1;
    });

    if (added < 1) return 0;

    syncThreadStateFromDom();
    list.scrollTop += list.scrollHeight - beforeHeight;
    if (typeof window.truxRefreshTimeAgo === "function") {
      window.truxRefreshTimeAgo();
    }

    return added;
  };

  const replaceMessageBubble = (targetId, html) => {
    if (state.quickActionsBubbleId === String(targetId || "")) {
      closeQuickActions();
    }

    const nextBubble = createNodeFromHtml(html, "[data-message-bubble='1']");
    if (!(nextBubble instanceof HTMLElement)) {
      return false;
    }

    const existingBubble = findMessageBubble(targetId);
    if (existingBubble instanceof HTMLElement) {
      existingBubble.replaceWith(nextBubble);
    } else {
      const list = getMessageList();
      if (!(list instanceof HTMLElement)) return false;
      removeMessageEmptyState();
      list.appendChild(nextBubble);
    }

    syncThreadStateFromDom();
    if (typeof window.truxRefreshTimeAgo === "function") {
      window.truxRefreshTimeAgo();
    }
    return true;
  };

  const updateHistory = (url, mode = "push") => {
    if (mode === "none") return;
    if (mode === "replace") {
      window.history.replaceState({}, "", url);
      return;
    }
    if (window.location.href !== url) {
      window.history.pushState({}, "", url);
    }
  };

  const stopPolling = () => {
    window.clearTimeout(state.pollTimer);
    state.pollTimer = 0;
  };

  const refreshThreadUi = (options = {}) => {
    closeAttachmentMenu();
    renderEmojiPicker();
    syncComposerHeight();
    setComposerReplyContext(state.replyContext);
    if (typeof window.truxInitMentionInputs === "function") {
      window.truxInitMentionInputs(layout);
    }
    if (typeof window.truxRefreshTimeAgo === "function") {
      window.truxRefreshTimeAgo();
    }
    updateConversationFilter();

    if (options.focusComposer) {
      const input = getComposerInput();
      if (input instanceof HTMLTextAreaElement) {
        window.setTimeout(() => {
          input.focus();
        }, 20);
      }
    }
  };

  const scheduleMarkConversationRead = (delay = 120) => {
    const conversationId = getActiveConversationId();
    if (
      !conversationId ||
      document.visibilityState === "hidden" ||
      (typeof document.hasFocus === "function" && !document.hasFocus())
    ) {
      return;
    }
    if (state.pendingRead) {
      return;
    }

    window.clearTimeout(state.readTimer);
    state.readTimer = window.setTimeout(() => {
      void markConversationRead(conversationId, { suppressErrors: true });
    }, delay);
  };

  const markConversationRead = async (conversationId, options = {}) => {
    if (!conversationId || state.pendingRead) return;

    const csrf = getCsrfToken();
    if (!csrf) return;

    state.pendingRead = true;
    try {
      const res = await fetch(`${baseUrl}/mark_conversation_read.php?format=json`, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new URLSearchParams({
          _csrf: csrf,
          id: String(conversationId),
        }),
      });
      const data = await readJson(res);
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not update read state.");
      }
      applyConversationPayload(data, { moveToTop: false });
      setActiveConversationState(conversationId);
    } catch (err) {
      if (!options.suppressErrors) {
        showToast(
          err instanceof Error ? err.message : "Could not update read state.",
          "error"
        );
      }
    } finally {
      state.pendingRead = false;
    }
  };

  const applyReadStatusUpdates = (statuses) => {
    if (!statuses || typeof statuses !== "object") return;
    const messageList = getMessageList();
    if (!(messageList instanceof HTMLElement)) return;
    Object.entries(statuses).forEach(([idStr, isRead]) => {
      const bubble = messageList.querySelector(
        `[data-message-bubble="1"][data-message-id="${CSS.escape(String(idStr))}"]`
      );
      if (!(bubble instanceof HTMLElement)) return;
      const statusEl = bubble.querySelector("[data-message-read-status]");
      if (!(statusEl instanceof HTMLElement)) return;
      const currentStatus = statusEl.getAttribute("data-message-read-status");
      const newStatus = isRead ? "read" : "sent";
      if (currentStatus === newStatus) return;
      statusEl.setAttribute("data-message-read-status", newStatus);
      statusEl.setAttribute("aria-label", isRead ? "Read" : "Delivered");
      if (isRead) {
        statusEl.innerHTML =
          '<svg class="messageBubble__readTick messageBubble__readTick--read" viewBox="0 0 20 14" aria-hidden="true" focusable="false" width="16" height="16">' +
          '<path d="M1 7 6 12 13 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
          '<path d="M7 7 12 12 19 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
          "</svg>";
      } else {
        statusEl.innerHTML =
          '<svg class="messageBubble__readTick messageBubble__readTick--sent" viewBox="0 0 14 14" aria-hidden="true" focusable="false" width="14" height="14">' +
          '<path d="M1 7 6 12 13 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
          "</svg>";
      }
    });
  };

  const schedulePoll = (delay = 2500) => {
    stopPolling();
    if (!getActiveConversationId() || document.visibilityState === "hidden") {
      return;
    }
    state.pollTimer = window.setTimeout(() => {
      void pollConversation();
    }, delay);
  };

  const pollConversation = async () => {
    const conversationId = getActiveConversationId();
    if (!conversationId || document.visibilityState === "hidden" || state.pollInFlight) {
      return;
    }

    state.pollInFlight = true;
    const latestBeforePoll = state.latestMessageId || 0;

    try {
      const pollUrl = new URL(`${baseUrl}/messages_poll.php`, window.location.origin);
      pollUrl.searchParams.set("conversation_id", String(conversationId));
      pollUrl.searchParams.set("after_message_id", String(latestBeforePoll));

      const res = await fetch(pollUrl.toString(), {
        method: "GET",
        headers: { Accept: "application/json" },
      });
      const data = await readJson(res);
      if (res.status === 401 && data?.login_url) {
        window.location.assign(String(data.login_url));
        return;
      }
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not refresh messages.");
      }

      if (conversationId !== getActiveConversationId()) {
        return;
      }

      const added = appendMessagesHtml(data.messages_html || "", {
        scrollBehavior: "smooth",
      });
      applyConversationPayload(data, { moveToTop: true });
      if (added > 0) {
        scheduleMarkConversationRead(80);
      }
      applyReadStatusUpdates(data.sent_read_statuses || {});
    } catch (err) {
      console.error(err);
    } finally {
      state.pollInFlight = false;
      if (conversationId === getActiveConversationId()) {
        schedulePoll(2500);
      }
    }
  };

  const setLoadOlderBusy = (busy) => {
    const button = getLoadOlderButton();
    if (!(button instanceof HTMLButtonElement)) return;
    button.disabled = busy;
    button.classList.toggle("is-loading", busy);
    button.textContent = busy ? "Loading..." : "Load older messages";
  };

  const loadOlderMessages = async (options = {}) => {
    if (state.loadingOlder || !state.oldestMessageId || !getActiveConversationId()) {
      return 0;
    }

    state.loadingOlder = true;
    setLoadOlderBusy(true);

    try {
      const url = new URL(`${baseUrl}/messages_before.php`, window.location.origin);
      url.searchParams.set("conversation_id", String(getActiveConversationId()));
      url.searchParams.set("before_message_id", String(state.oldestMessageId));

      const res = await fetch(url.toString(), {
        method: "GET",
        headers: { Accept: "application/json" },
      });
      const data = await readJson(res);
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not load older messages.");
      }

      const added = prependMessagesHtml(data.messages_html || "");
      state.hasMoreBefore = !!data.has_more;
      updateThreadCursorData();
      return added;
    } catch (err) {
      if (!options.silent) {
        showToast(
          err instanceof Error ? err.message : "Could not load older messages.",
          "error"
        );
      }
      return 0;
    } finally {
      state.loadingOlder = false;
      setLoadOlderBusy(false);
    }
  };

  const formatBytes = (bytes) => {
    const value = Math.max(0, Number(bytes || 0));
    if (value >= 1024 * 1024) {
      return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }
    if (value >= 1024) {
      return `${(value / 1024).toFixed(1)} KB`;
    }
    return `${value} B`;
  };

  const getMaxAttachments = () => {
    const form = getComposerForm();
    if (!(form instanceof HTMLFormElement)) return 10;
    const max = Number(form.dataset.messagesMaxAttachments || "10");
    return Number.isFinite(max) && max > 0 ? max : 10;
  };

  const attachmentMimeLooksAllowed = (file) => {
    const mime = String(file?.type || "").trim().toLowerCase();
    const name = String(file?.name || "").trim().toLowerCase();
    if (mime.startsWith("image/")) {
      return true;
    }
    if (mime === "application/pdf") {
      return true;
    }
    return /\.(jpe?g|png|gif|webp|pdf)$/i.test(name);
  };

  const renderAttachmentPreview = () => {
    const preview = getAttachmentPreview();
    const form = getComposerForm();
    if (!(preview instanceof HTMLElement) || !(form instanceof HTMLFormElement)) {
      return;
    }

    if (!state.selectedAttachments.length) {
      preview.hidden = true;
      preview.innerHTML = "";
      form.classList.remove("has-attachments");
      return;
    }

    preview.hidden = false;
    form.classList.add("has-attachments");
    preview.innerHTML = state.selectedAttachments
      .map((item) => {
        const file = item.file;
        const kind =
          String(file.type || "").toLowerCase() === "application/pdf" ||
          /\.pdf$/i.test(file.name || "")
            ? "PDF"
            : "Photo";
        return `
          <div class="messagesComposer__file" data-attachment-key="${escapeHtml(item.id)}">
            <span class="messagesComposer__fileType">${escapeHtml(kind)}</span>
            <span class="messagesComposer__fileMeta">
              <strong>${escapeHtml(file.name || "Attachment")}</strong>
              <span>${escapeHtml(formatBytes(file.size || 0))}</span>
            </span>
            <button class="messagesComposer__fileRemove" type="button" aria-label="Remove attachment" data-messages-attachment-remove="1" data-attachment-key="${escapeHtml(item.id)}">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              </svg>
            </button>
          </div>
        `;
      })
      .join("");
  };

  const clearSelectedAttachments = () => {
    state.selectedAttachments = [];
    const input = getAttachmentInput();
    if (input instanceof HTMLInputElement) {
      input.value = "";
    }
    renderAttachmentPreview();
  };

  const addSelectedAttachments = (files) => {
    const incoming = Array.from(files || []).filter((file) => file instanceof File);
    if (!incoming.length) return;

    const maxAttachments = getMaxAttachments();
    const errors = [];
    incoming.forEach((file) => {
      if (state.selectedAttachments.length >= maxAttachments) {
        errors.push(`You can attach up to ${maxAttachments} files per message.`);
        return;
      }
      if (!attachmentMimeLooksAllowed(file)) {
        errors.push(`${file.name} is not an accepted image or PDF.`);
        return;
      }
      if (Number(file.size || 0) > MAX_CLIENT_ATTACHMENT_BYTES) {
        errors.push(`${file.name} exceeds the 4 MB per-file limit.`);
        return;
      }
      state.selectedAttachments.push({
        id: `attachment-${Date.now()}-${++state.attachmentCounter}`,
        file,
      });
    });

    renderAttachmentPreview();
    if (errors.length) {
      showToast(errors[0], "error");
    }
  };

  const removeSelectedAttachment = (attachmentId) => {
    state.selectedAttachments = state.selectedAttachments.filter(
      (item) => item.id !== attachmentId
    );
    renderAttachmentPreview();
  };

  const clearComposerDraft = () => {
    const textarea = getComposerInput();
    if (textarea instanceof HTMLTextAreaElement) {
      textarea.value = "";
      delete textarea.dataset.imeComposing;
      syncComposerHeight(textarea);
    }
    closeAttachmentMenu();
    closeEmojiPanel();
    clearSelectedAttachments();
    clearComposerReplyContext();
  };

  const showComposerPlaceholder = (action) => {
    const messages = {
      video: "Video attachments are coming soon.",
      voice: "Voice messages coming soon.",
    };
    showToast(messages[String(action || "")] || "This action is coming soon.", "info");
  };

  const buildReplyContextMarkup = (replyContext) => {
    const messageId = Number(replyContext?.messageId || 0);
    const senderUsername = String(replyContext?.senderUsername || "").trim();
    const preview = truncateText(replyContext?.preview || "", 60);
    const senderLabel = senderUsername ? `@${senderUsername}` : "deleted message";
    if (!messageId || !preview) {
      return "";
    }

    return `
      <button
        class="messageBubble__replyContext"
        type="button"
        data-message-reply-jump="1"
        data-target-message-id="${escapeHtml(String(messageId))}"
        aria-label="Jump to the original message from ${escapeHtml(senderLabel)}">
        <span class="messageBubble__replyContextLabel">Replying to ${escapeHtml(
          senderLabel
        )}</span>
        <span class="messageBubble__replyContextPreview">${escapeHtml(preview)}</span>
      </button>
    `;
  };

  const extractMessagePreviewFromBubble = (bubble) => {
    if (!(bubble instanceof HTMLElement)) {
      return "";
    }
    if (bubble.dataset.messageIsUnsent === "1") {
      return "Message deleted.";
    }

    const rawBody = String(bubble.dataset.messageBodyRaw || "").trim();
    if (rawBody) {
      return truncateText(rawBody, 60);
    }

    const fileLabel = bubble.querySelector(".messageBubble__fileMeta strong");
    if (fileLabel instanceof HTMLElement) {
      return truncateText(fileLabel.textContent || "Attachment", 60);
    }

    const attachment = bubble.querySelector(".messageBubble__attachment");
    if (attachment instanceof HTMLElement) {
      return "Attachment";
    }

    return "";
  };

  const buildReplyContextFromBubble = (bubble) => {
    if (!(bubble instanceof HTMLElement)) {
      return null;
    }

    const messageId = Number(getBubbleId(bubble));
    const senderUsername = String(bubble.dataset.messageSenderUsername || "").trim();
    const preview = extractMessagePreviewFromBubble(bubble);
    if (!messageId || !preview) {
      return null;
    }

    return {
      messageId,
      senderUsername,
      preview,
    };
  };

  const updateMessageReactionUi = (bubble, likeCount, viewerLiked) => {
    if (!(bubble instanceof HTMLElement)) return;
    const nextCount = Math.max(0, Number(likeCount || 0));
    const liked = !!viewerLiked;

    bubble.dataset.messageLikeCount = String(nextCount);
    bubble.dataset.messageViewerLiked = liked ? "1" : "0";

    const badge = bubble.querySelector("[data-message-reaction-badge='1']");
    const countNode = bubble.querySelector("[data-message-reaction-count='1']");
    if (countNode instanceof HTMLElement) {
      countNode.textContent = String(nextCount);
    }
    if (badge instanceof HTMLElement) {
      badge.hidden = nextCount <= 0;
    }

    bubble
      .querySelectorAll("[data-message-quick-action='react']")
      .forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        button.classList.toggle("is-active", liked);
        button.setAttribute("aria-pressed", liked ? "true" : "false");
      });
  };

  const hideDeleteConfirmation = (bubble) => {
    if (!(bubble instanceof HTMLElement)) return;
    const confirm = bubble.querySelector("[data-message-delete-confirm='1']");
    if (confirm instanceof HTMLElement) {
      confirm.hidden = true;
    }
    bubble.classList.remove("is-delete-confirm-open");
  };

  const showDeleteConfirmation = (bubble) => {
    if (!(bubble instanceof HTMLElement)) return;
    document
      .querySelectorAll("[data-message-delete-confirm='1']")
      .forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        node.hidden = true;
        node.closest("[data-message-bubble='1']")?.classList.remove(
          "is-delete-confirm-open"
        );
      });

    const confirm = bubble.querySelector("[data-message-delete-confirm='1']");
    if (!(confirm instanceof HTMLElement)) return;
    confirm.hidden = false;
    bubble.classList.add("is-delete-confirm-open");
  };

  const flashMessageBubble = (bubble) => {
    if (!(bubble instanceof HTMLElement)) return;
    bubble.classList.remove("messageBubble--replyTarget");
    void bubble.offsetWidth;
    bubble.classList.add("messageBubble--replyTarget");
    window.setTimeout(() => {
      bubble.classList.remove("messageBubble--replyTarget");
    }, 1800);
  };

  const jumpToReplyTarget = async (messageId) => {
    const targetId = Number(messageId || 0);
    if (!targetId) return;

    let bubble = findMessageBubble(targetId);
    while (!(bubble instanceof HTMLElement) && state.hasMoreBefore) {
      const added = await loadOlderMessages({ silent: true });
      if (added < 1) {
        break;
      }
      bubble = findMessageBubble(targetId);
    }

    if (!(bubble instanceof HTMLElement)) {
      showToast("The original message is unavailable.", "error");
      return;
    }

    bubble.scrollIntoView({
      behavior: "smooth",
      block: "center",
      inline: "nearest",
    });
    flashMessageBubble(bubble);
  };

  const buildTemporaryAttachmentMarkup = (attachments) =>
    attachments
      .map((attachment) => {
        const file = attachment.file;
        const isPdf =
          String(file.type || "").toLowerCase() === "application/pdf" ||
          /\.pdf$/i.test(file.name || "");
        return `
          <div class="messageBubble__attachment messageBubble__attachment--file messageBubble__attachment--pending">
            <div class="messageBubble__fileMeta">
              <strong>${escapeHtml(file.name || (isPdf ? "PDF attachment" : "Photo"))}</strong>
              <span class="muted">${escapeHtml(isPdf ? "PDF" : "Image")} &middot; ${escapeHtml(
                formatBytes(file.size || 0)
              )}</span>
            </div>
          </div>
        `;
      })
      .join("");

  const createTemporaryMessageMarkup = (payload, tempId, status = "sending") => {
    const now = new Date();
    const dayKey = formatMessageDayKey(now);
    const dayLabel = formatMessageDayLabel(now);
    const isFailed = status === "failed";
    const statusLabel = isFailed ? "Failed" : "Sending";
    const bodyMarkup =
      payload.body !== ""
        ? `<div class="messageBubble__body">${textToHtml(payload.body)}</div>`
        : "";
    const replyMarkup = buildReplyContextMarkup(payload.replyContext);
    const attachmentMarkup = payload.attachments.length
      ? `<div class="messageBubble__attachments">${buildTemporaryAttachmentMarkup(
          payload.attachments
        )}</div>`
      : "";

    return `
      <article
        class="messageBubble messageBubble--mine messageBubble--${isFailed ? "failed" : "sending"}"
        data-message-bubble="1"
        data-message-id="${escapeHtml(tempId)}"
        data-message-day-key="${escapeHtml(dayKey)}"
        data-message-day-label="${escapeHtml(dayLabel)}"
        data-message-exact-time="${escapeHtml(now.toLocaleString())}"
        data-message-body-raw="${escapeHtml(payload.body)}"
        data-message-sender-username="${escapeHtml(payload.senderUsername || "you")}"
        data-message-reply-to-id="${escapeHtml(
          String(payload.replyToMessageId || 0)
        )}"
        data-message-like-count="0"
        data-message-viewer-liked="0"
        data-message-can-edit="0"
        data-message-can-unsend="0"
        data-message-is-unsent="0">
        <div class="messageBubble__meta">
          <div class="messageBubble__metaMain">
            <span class="messageBubble__author">You</span>
            <span class="muted">just now</span>
            <span class="messageBubble__statusTag" data-temp-status="1">${escapeHtml(
              statusLabel
            )}</span>
          </div>
        </div>
        <div class="messageBubble__content" data-message-content="1">
          ${replyMarkup}
          ${bodyMarkup}
          ${attachmentMarkup}
        </div>
        <div class="messageBubble__hoverActions" data-message-hover-actions="1" aria-label="Quick message actions">
          <button class="messageBubble__hoverAction" type="button" data-message-quick-action="react" aria-label="React to message" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M8.25 10.75V20H6.5a1.75 1.75 0 0 1-1.75-1.75v-5.75A1.75 1.75 0 0 1 6.5 10.75h1.75Zm2.5 9.25h4.65a2.6 2.6 0 0 0 2.52-2l1.05-4.55a2.27 2.27 0 0 0-2.22-2.8h-3v-4A2.4 2.4 0 0 0 11.35 4.3L9.7 8.05a5.37 5.37 0 0 0-.45 2.18v8.02c0 .97.78 1.75 1.75 1.75Z" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button class="messageBubble__hoverAction" type="button" data-message-quick-action="reply" aria-label="Reply to message">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M10.25 8.25 5.75 12l4.5 3.75M6.25 12h7.5a4.5 4.5 0 0 1 4.5 4.5v.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <div class="messageBubble__reactionBadge" hidden data-message-reaction-badge="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M8.25 10.75V20H6.5a1.75 1.75 0 0 1-1.75-1.75v-5.75A1.75 1.75 0 0 1 6.5 10.75h1.75Zm2.5 9.25h4.65a2.6 2.6 0 0 0 2.52-2l1.05-4.55a2.27 2.27 0 0 0-2.22-2.8h-3v-4A2.4 2.4 0 0 0 11.35 4.3L9.7 8.05a5.37 5.35 0 0 0-.45 2.18v8.02c0 .97.78 1.75 1.75 1.75Z" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span data-message-reaction-count="1">0</span>
        </div>
        <div class="messageBubble__failure" data-temp-failure="1"${
          isFailed ? "" : " hidden"
        }>
          <span class="muted">This message could not be sent.</span>
          <button class="shellButton shellButton--ghost" type="button" data-message-retry="1" data-temp-message-id="${escapeHtml(
            tempId
          )}">
            Retry
          </button>
        </div>
      </article>
    `;
  };

  const insertTemporaryMessage = (payload) => {
    const tempId = `temp-${Date.now()}-${++state.tempMessageCounter}`;
    appendMessagesHtml(createTemporaryMessageMarkup(payload, tempId), {
      forceScroll: true,
      bumpJump: false,
      scrollBehavior: "smooth",
    });
    state.failedMessages.set(tempId, payload);
    return tempId;
  };

  const setTemporaryMessageState = (tempId, status) => {
    const bubble = findMessageBubble(tempId);
    if (!(bubble instanceof HTMLElement)) return;
    const failure = bubble.querySelector("[data-temp-failure='1']");
    const statusNode = bubble.querySelector("[data-temp-status='1']");

    bubble.classList.toggle("messageBubble--sending", status === "sending");
    bubble.classList.toggle("messageBubble--failed", status === "failed");

    if (statusNode instanceof HTMLElement) {
      statusNode.textContent = status === "failed" ? "Failed" : "Sending";
    }
    if (failure instanceof HTMLElement) {
      failure.hidden = status !== "failed";
    }
  };

  const buildComposerPayload = (form) => {
    if (!(form instanceof HTMLFormElement)) {
      return null;
    }

    const textarea = form.querySelector("[data-messages-input='1']");
    if (!(textarea instanceof HTMLTextAreaElement)) {
      return null;
    }

    const bodyText = textarea.value.trim();
    const conversationInput = form.querySelector("input[name='conversation_id']");
    const recipientInputNode = form.querySelector("input[name='recipient_id']");
    const replyInput = getComposerReplyInput();

    return {
      body: bodyText,
      conversationId:
        conversationInput instanceof HTMLInputElement
          ? Number(conversationInput.value || "0")
          : 0,
      recipientId:
        recipientInputNode instanceof HTMLInputElement
          ? Number(recipientInputNode.value || "0")
          : 0,
      replyToMessageId:
        replyInput instanceof HTMLInputElement
          ? Number(replyInput.value || "0")
          : 0,
      replyContext: state.replyContext,
      senderUsername: "you",
      attachments: state.selectedAttachments.map((item) => ({
        id: item.id,
        file: item.file,
      })),
    };
  };

  const submitSendPayload = async (payload, tempId) => {
    const form = getComposerForm();
    if (!(form instanceof HTMLFormElement)) return;

    setComposerBusy(form, true);

    try {
      const requestUrl = new URL(form.action, window.location.origin);
      requestUrl.searchParams.set("format", "json");

      const csrf = getCsrfToken();
      const formData = new FormData();
      if (csrf) {
        formData.set("_csrf", csrf);
      }
      if (payload.conversationId > 0) {
        formData.set("conversation_id", String(payload.conversationId));
      } else if (payload.recipientId > 0) {
        formData.set("recipient_id", String(payload.recipientId));
      }
      if (payload.replyToMessageId > 0) {
        formData.set("reply_to_message_id", String(payload.replyToMessageId));
      }
      formData.set("body", payload.body);
      payload.attachments.forEach((attachment) => {
        formData.append("attachments[]", attachment.file, attachment.file.name);
      });

      const res = await fetch(requestUrl.toString(), {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      });
      const data = await readJson(res);

      if (res.status === 401 && data?.login_url) {
        window.location.assign(String(data.login_url));
        return;
      }
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not send message.");
      }

      const nextConversationId = Number(data.conversation_id || 0);
      const conversationUrl = String(
        data.conversation_url || `${baseUrl}/messages.php`
      ).trim();

      applyConversationPayload(data, { moveToTop: true });
      updateThreadStatusCopy("Private thread");

      if (nextConversationId > 0) {
        setActiveConversationState(nextConversationId);
        setComposerConversationId(nextConversationId);
      }

      if (
        data.created_conversation ||
        !replaceMessageBubble(tempId, data.message_html || "")
      ) {
        const tempBubble = findMessageBubble(tempId);
        if (tempBubble instanceof HTMLElement) {
          tempBubble.remove();
        }
        syncThreadStateFromDom();

        if (conversationUrl) {
          await loadConversation(conversationUrl, {
            historyMode: "replace",
            forceThreadOpen: true,
            focusComposer: true,
          });
          return;
        }
      } else {
        updateHistory(conversationUrl || window.location.href, "replace");
        window.requestAnimationFrame(() => {
          scrollMessageListToLatest("smooth");
        });
        scheduleMarkConversationRead(80);
      }

      state.failedMessages.delete(tempId);
    } catch (err) {
      setTemporaryMessageState(tempId, "failed");
      state.failedMessages.set(tempId, payload);
      showToast(err instanceof Error ? err.message : "Could not send message.", "error");
    } finally {
      setComposerBusy(form, false);
      refreshThreadUi({ focusComposer: true });
    }
  };

  const submitComposer = async (form) => {
    if (!(form instanceof HTMLFormElement) || isComposerBusy(form)) {
      return;
    }

    closeAttachmentMenu();
    const payload = buildComposerPayload(form);
    if (!payload) {
      return;
    }

    if (payload.body === "" && payload.attachments.length < 1) {
      showToast("Message must contain text or attachments.", "error");
      return;
    }

    const tempId = insertTemporaryMessage(payload);
    clearComposerDraft();
    await submitSendPayload(payload, tempId);
  };

  const retryFailedMessage = async (tempId) => {
    if (!tempId || isComposerBusy()) {
      return;
    }
    const payload = state.failedMessages.get(tempId);
    if (!payload) {
      showToast("The original message draft is no longer available.", "error");
      return;
    }
    setTemporaryMessageState(tempId, "sending");
    await submitSendPayload(payload, tempId);
  };

  const exitMessageEditMode = () => {
    if (!state.editingMessageId) {
      return;
    }

    const bubble = findMessageBubble(state.editingMessageId);
    if (bubble instanceof HTMLElement) {
      const content = bubble.querySelector("[data-message-content='1']");
      if (content instanceof HTMLElement) {
        content.innerHTML = state.editingRestoreHtml;
      }
      bubble.classList.remove("messageBubble--editing");
    }

    state.editingMessageId = "";
    state.editingRestoreHtml = "";
  };

  const enterMessageEditMode = (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    if (bubble.dataset.messageCanEdit !== "1") {
      showToast("The edit window has expired.", "error");
      return;
    }

    exitMessageEditMode();

    const content = bubble.querySelector("[data-message-content='1']");
    if (!(content instanceof HTMLElement)) return;

    const originalBody = String(bubble.dataset.messageBodyRaw || "");
    const attachments = content.querySelector(".messageBubble__attachments");
    const attachmentsMarkup =
      attachments instanceof HTMLElement ? attachments.outerHTML : "";

    state.editingMessageId = String(messageId);
    state.editingRestoreHtml = content.innerHTML;
    bubble.classList.add("messageBubble--editing");

    content.innerHTML = `
      <form class="messageBubble__editForm" data-message-edit-form="1" data-message-id="${escapeHtml(
        String(messageId)
      )}">
        <textarea rows="1" maxlength="2000" data-message-edit-input="1">${escapeHtml(
          originalBody
        )}</textarea>
        <div class="messageBubble__editActions">
          <button class="shellButton shellButton--accent" type="submit">Save</button>
          <button class="shellButton shellButton--ghost" type="button" data-message-edit-cancel="1">Cancel</button>
        </div>
      </form>
      ${
        attachmentsMarkup
          ? `<div class="messageBubble__editHint muted">Attachments remain unchanged.</div>${attachmentsMarkup}`
          : ""
      }
    `;

    const textarea = content.querySelector("[data-message-edit-input='1']");
    if (textarea instanceof HTMLTextAreaElement) {
      syncComposerHeight(textarea);
      window.setTimeout(() => {
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
      }, 20);
    }
  };

  const submitMessageEdit = async (form) => {
    if (!(form instanceof HTMLFormElement)) return;

    const messageId = String(form.dataset.messageId || "").trim();
    const textarea = form.querySelector("[data-message-edit-input='1']");
    if (!(textarea instanceof HTMLTextAreaElement) || !messageId) {
      return;
    }

    const nextBody = textarea.value.trim();
    if (!nextBody) {
      showToast("Message must be 1-2000 characters.", "error");
      return;
    }

    const csrf = getCsrfToken();
    if (!csrf) {
      showToast("Session expired. Refresh the page and try again.", "error");
      exitMessageEditMode();
      return;
    }

    try {
      const res = await fetch(`${baseUrl}/edit_message.php?format=json`, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new URLSearchParams({
          _csrf: csrf,
          message_id: String(messageId),
          body: nextBody,
        }),
      });
      const data = await readJson(res);
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not edit message.");
      }

      state.editingMessageId = "";
      state.editingRestoreHtml = "";
      replaceMessageBubble(messageId, data.message_html || "");
      applyConversationPayload(data, { moveToTop: false });
      scheduleMarkConversationRead(80);
    } catch (err) {
      exitMessageEditMode();
      showToast(err instanceof Error ? err.message : "Could not edit message.", "error");
    }
  };

  const toggleMessageReaction = async (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    const csrf = getCsrfToken();
    if (!csrf) {
      showToast("Session expired. Refresh the page and try again.", "error");
      return;
    }

    try {
      const res = await fetch(`${baseUrl}/react_message.php?format=json`, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new URLSearchParams({
          _csrf: csrf,
          message_id: String(messageId),
          reaction: "like",
        }),
      });
      const data = await readJson(res);
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not update message reaction.");
      }

      updateMessageReactionUi(
        bubble,
        Number(data.like_count || 0),
        !!data.viewer_liked
      );
    } catch (err) {
      showToast(
        err instanceof Error ? err.message : "Could not update message reaction.",
        "error"
      );
    }
  };

  const beginReplyToMessage = (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;

    const replyContext = buildReplyContextFromBubble(bubble);
    if (!replyContext) {
      showToast("Could not build that reply preview.", "error");
      return;
    }

    setComposerReplyContext(replyContext);
    closeEmojiPanel();
    closeAttachmentMenu();

    const input = getComposerInput();
    if (input instanceof HTMLTextAreaElement) {
      window.setTimeout(() => {
        input.focus();
      }, 20);
    }
  };

  const performDeleteMessage = async (messageId) => {
    if (!messageId) return;
    const csrf = getCsrfToken();
    if (!csrf) {
      showToast("Session expired. Refresh the page and try again.", "error");
      return;
    }

    try {
      const res = await fetch(`${baseUrl}/unsend_message.php?format=json`, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new URLSearchParams({
          _csrf: csrf,
          message_id: String(messageId),
        }),
      });
      const data = await readJson(res);
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not delete message.");
      }

      if (state.editingMessageId === String(messageId)) {
        state.editingMessageId = "";
        state.editingRestoreHtml = "";
      }

      replaceMessageBubble(messageId, data.message_html || "");
      applyConversationPayload(data, { moveToTop: false });
      closeQuickActions();
      scheduleMarkConversationRead(80);
    } catch (err) {
      showToast(
        err instanceof Error ? err.message : "Could not delete. Try again.",
        "error"
      );
    }
  };

  const requestDeleteMessage = (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    showDeleteConfirmation(bubble);
    openQuickActions(bubble, { mobile: state.quickActionsMode === "mobile" });
  };

  const showMessageTimestamp = (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    const exactTime = String(bubble.dataset.messageExactTime || "").trim();
    if (exactTime) {
      showToast(exactTime);
    }
  };

  const copyMessageBody = async (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    const rawBody = String(bubble.dataset.messageBodyRaw || "").trim();
    if (!rawBody) return;

    try {
      await copyText(rawBody);
      showToast("Message copied");
    } catch {
      showToast("Could not copy this message.", "error");
    }
  };

  const openReportForMessage = (messageId) => {
    const bubble = findMessageBubble(messageId);
    if (!(bubble instanceof HTMLElement)) return;
    const reportButton = bubble.querySelector("[data-report-action='1']");
    if (reportButton instanceof HTMLButtonElement) {
      reportButton.click();
    }
  };

  const openBubbleActionsSheet = (messageId) => {
    const bubble = findMessageBubble(messageId);
    const sheet = getBubbleActionsSheet();
    if (!(bubble instanceof HTMLElement) || !(sheet instanceof HTMLElement)) {
      return;
    }

    sheet.dataset.messageId = String(messageId);

    const exactTime = String(bubble.dataset.messageExactTime || "").trim();
    const isMine = bubble.classList.contains("messageBubble--mine");
    const canEdit = bubble.dataset.messageCanEdit === "1";
    const canUnsend = bubble.dataset.messageCanUnsend === "1";
    const canCopy = String(bubble.dataset.messageBodyRaw || "").trim() !== "";
    const hasReport = bubble.querySelector("[data-report-action='1']") instanceof HTMLElement;

    const title = sheet.querySelector("[data-message-sheet-title='1']");
    const meta = sheet.querySelector("[data-message-sheet-meta='1']");
    const copyButton = sheet.querySelector("[data-message-sheet-copy='1']");
    const editButton = sheet.querySelector("[data-message-sheet-edit='1']");
    const unsendButton = sheet.querySelector("[data-message-sheet-unsend='1']");
    const reportButton = sheet.querySelector("[data-message-sheet-report='1']");
    const timeButton = sheet.querySelector("[data-message-sheet-time='1']");

    if (title instanceof HTMLElement) {
      title.textContent = isMine ? "Your message" : "Message";
    }
    if (meta instanceof HTMLElement) {
      meta.textContent = exactTime || "Choose how to handle this message.";
    }
    if (copyButton instanceof HTMLElement) {
      copyButton.hidden = !canCopy;
    }
    if (editButton instanceof HTMLElement) {
      editButton.hidden = !canEdit;
    }
    if (unsendButton instanceof HTMLElement) {
      unsendButton.hidden = !canUnsend;
    }
    if (reportButton instanceof HTMLElement) {
      reportButton.hidden = !hasReport;
    }
    if (timeButton instanceof HTMLElement) {
      timeButton.hidden = exactTime === "";
    }

    openSheet("message-bubble-actions");
  };

  const applyFetchedConversation = (doc, options = {}) => {
    const partialRoot =
      doc.querySelector("[data-messages-thread-response='1']") ||
      doc.querySelector("[data-messages-layout='1']");
    if (!(partialRoot instanceof HTMLElement)) return false;

    const nextThread = partialRoot.querySelector("[data-messages-thread='1']");
    const currentThread = getThreadPanel();
    if (!(nextThread instanceof HTMLElement) || !(currentThread instanceof HTMLElement)) {
      return false;
    }
    currentThread.replaceWith(nextThread);

    const nextMobileState = partialRoot.querySelector(".messagesMobileBar__state--thread");
    const currentMobileState = getMobileThreadState();
    if (nextMobileState instanceof HTMLElement && currentMobileState instanceof HTMLElement) {
      currentMobileState.replaceWith(nextMobileState);
    }

    const nextThreadActionsSheet = partialRoot.querySelector("[data-shell-sheet='message-actions']");
    const currentThreadActionsSheet = getThreadActionsSheet();
    if (
      nextThreadActionsSheet instanceof HTMLElement &&
      currentThreadActionsSheet instanceof HTMLElement
    ) {
      currentThreadActionsSheet.replaceWith(nextThreadActionsSheet);
    }

    const nextBubbleActionsSheet = partialRoot.querySelector(
      "[data-shell-sheet='message-bubble-actions']"
    );
    const currentBubbleActionsSheet = getBubbleActionsSheet();
    if (
      nextBubbleActionsSheet instanceof HTMLElement &&
      currentBubbleActionsSheet instanceof HTMLElement
    ) {
      currentBubbleActionsSheet.replaceWith(nextBubbleActionsSheet);
    }

    state.editingMessageId = "";
    state.editingRestoreHtml = "";
    state.selectedAttachments = [];
    state.failedMessages.clear();
    closeQuickActions();
    clearComposerReplyContext();
    closeAttachmentMenu();
    closeEmojiPanel();
    clearThreadTransportState();

    const nextConversationId = Number(
      partialRoot.getAttribute("data-messages-active-conversation-id") || "0"
    );
    setActiveConversationState(nextConversationId);
    setThreadOpen(
      options.forceThreadOpen != null
        ? !!options.forceThreadOpen
        : partialRoot.getAttribute("data-thread-open") === "1"
    );

    const nextTitle =
      String(partialRoot.getAttribute("data-document-title") || "").trim() ||
      doc.title;
    if (nextTitle) {
      document.title = nextTitle;
    }

    syncThreadStateFromDom();
    refreshThreadUi({ focusComposer: !!options.focusComposer });
    window.requestAnimationFrame(() => {
      scrollMessageListToLatest("auto");
    });
    scheduleMarkConversationRead(80);
    schedulePoll(2500);
    return true;
  };

  const loadConversation = async (url, options = {}) => {
    const nextUrl = toMessagesUrl(url);
    if (!(nextUrl instanceof URL)) {
      return;
    }
    const partialUrl = toThreadPartialUrl(nextUrl);
    if (!(partialUrl instanceof URL)) return;

    stopPolling();
    closeSheet("message-bubble-actions");
    closeAttachmentMenu();
    closeEmojiPanel();

    if (state.threadAbort instanceof AbortController) {
      state.threadAbort.abort();
    }

    state.threadAbort = new AbortController();
    state.threadRequestId += 1;
    const requestId = state.threadRequestId;
    const nextUrlString = nextUrl.toString();
    const partialUrlString = partialUrl.toString();

    setThreadBusy(true);
    showThreadTransportLoading();

    try {
      const res = await fetch(partialUrlString, {
        method: "GET",
        headers: {
          Accept: "text/html",
          "X-Requested-With": "XMLHttpRequest",
        },
        signal: state.threadAbort.signal,
      });

      const html = await res.text();
      if (!res.ok) {
        throw new Error(
          extractThreadErrorMessage(html, "Failed to load conversation. Retry?")
        );
      }

      const doc = new DOMParser().parseFromString(html, "text/html");
      const applied = applyFetchedConversation(doc, {
        forceThreadOpen:
          options.forceThreadOpen != null
            ? options.forceThreadOpen
            : urlHasThreadState(nextUrlString),
        focusComposer: !!options.focusComposer,
      });

      if (!applied) {
        throw new Error("Failed to load conversation. Retry?");
      }

      updateHistory(nextUrlString, options.historyMode || "push");
    } catch (err) {
      if (err?.name === "AbortError") {
        return;
      }
      showThreadTransportError(
        err instanceof Error ? err.message : "Failed to load conversation. Retry?",
        nextUrlString
      );
    } finally {
      if (requestId === state.threadRequestId) {
        setThreadBusy(false);
      }
    }
  };

  const avatarThemeFor = (seed) => {
    const themes = ["accent", "mint", "warning", "danger"];
    const value = String(seed || "").trim().toLowerCase();
    if (!value) return themes[0];

    let score = 0;
    for (const char of value) {
      score = (score + char.charCodeAt(0)) % themes.length;
    }

    return themes[score] || themes[0];
  };

  const renderRecipientAvatar = (username, avatarUrl, label) => {
    const theme = avatarThemeFor(username || label);
    const initialSource = String(username || label || "T").trim();
    const initial = escapeHtml(initialSource.charAt(0).toUpperCase() || "T");

    if (String(avatarUrl || "").trim() !== "") {
      return `
        <span class="messagesRecipientResult__avatar dmAvatar dmAvatar--${theme} dmAvatar--image" aria-hidden="true">
          <img class="messagesRecipientResult__avatar__image dmAvatar__image" src="${escapeHtml(avatarUrl)}" alt="" loading="lazy" decoding="async">
        </span>
      `;
    }

    return `
      <span class="messagesRecipientResult__avatar dmAvatar dmAvatar--${theme}" aria-hidden="true">
        <span class="messagesRecipientResult__avatar__fallback dmAvatar__fallback">${initial}</span>
      </span>
    `;
  };

  const setRecipientStatus = (message, tone = "") => {
    if (!(recipientStatus instanceof HTMLElement)) return;
    recipientStatus.textContent = String(message || "");
    recipientStatus.classList.toggle("is-error", tone === "error");
  };

  const clearRecipientResults = () => {
    if (recipientResults instanceof HTMLElement) {
      recipientResults.innerHTML = "";
    }
  };

  const renderRecipientResults = (items) => {
    if (!(recipientResults instanceof HTMLElement)) return;

    if (!Array.isArray(items) || !items.length) {
      clearRecipientResults();
      return;
    }

    recipientResults.innerHTML = items
      .map((item) => {
        const username = String(item?.username || "").trim();
        if (!username) return "";

        const displayName = String(item?.display_name || "").trim();
        const conversationId = Number(item?.conversation_id || 0);
        const targetUrl = String(item?.target_url || "").trim();
        const label = `@${username}`;
        const metaLabel = conversationId > 0 ? "Existing thread" : "New thread";
        const copy =
          displayName !== ""
            ? `${escapeHtml(displayName)}`
            : conversationId > 0
              ? "Open your latest conversation."
              : "Start a new direct message.";

        return `
          <a class="messagesRecipientResult" href="${escapeHtml(targetUrl)}">
            ${renderRecipientAvatar(username, String(item?.avatar_url || ""), label)}
            <span class="messagesRecipientResult__copy">
              <strong>${escapeHtml(label)}</strong>
              <span>${copy}</span>
            </span>
            <span class="messagesRecipientResult__meta">${escapeHtml(metaLabel)}</span>
          </a>
        `;
      })
      .join("");
  };

  const loadRecipients = async (query) => {
    if (!(recipientInput instanceof HTMLInputElement)) return;
    if (!(recipientResults instanceof HTMLElement)) return;

    if (state.recipientAbort instanceof AbortController) {
      state.recipientAbort.abort();
    }
    state.recipientAbort = new AbortController();

    setRecipientStatus("Searching...");
    clearRecipientResults();

    try {
      const res = await fetch(
        `${baseUrl}/message_recipients.php?format=json&q=${encodeURIComponent(query)}`,
        {
          method: "GET",
          headers: { Accept: "application/json" },
          signal: state.recipientAbort.signal,
        }
      );

      const data = await res.json().catch(() => ({}));
      if (res.status === 401 && data?.login_url) {
        window.location.href = String(data.login_url);
        return;
      }
      if (!res.ok || !data?.ok) {
        throw new Error(data?.error || "Could not load recipients.");
      }

      const recipients = Array.isArray(data?.recipients) ? data.recipients : [];
      if (!recipients.length) {
        clearRecipientResults();
        setRecipientStatus("No users found for that username.");
        return;
      }

      renderRecipientResults(recipients);
      setRecipientStatus(
        recipients.length === 1
          ? "1 match found."
          : `${recipients.length} matches found.`
      );
    } catch (err) {
      if (err?.name === "AbortError") {
        return;
      }
      clearRecipientResults();
      setRecipientStatus(
        err instanceof Error ? err.message : "Could not load recipients.",
        "error"
      );
    }
  };

  const clearLongPress = () => {
    window.clearTimeout(state.longPressTimer);
    state.longPressTimer = 0;
    state.longPressBubbleId = "";
  };

  const normalizeToInboxUrl = () => {
    const nextUrl = getInboxUrl();
    window.history.replaceState({}, "", nextUrl);
    return nextUrl;
  };

  setThreadOpen(layout.classList.contains("is-thread-open"));
  syncThreadStateFromDom();
  refreshThreadUi();
  window.requestAnimationFrame(() => {
    scrollMessageListToLatest("auto");
  });
  scheduleMarkConversationRead(80);
  schedulePoll(2500);

  if (searchInput instanceof HTMLInputElement) {
    searchInput.addEventListener("input", scheduleConversationFilter);
  }

  if (recipientInput instanceof HTMLInputElement) {
    recipientInput.addEventListener("input", () => {
      const query = recipientInput.value.trim();
      window.clearTimeout(state.recipientTimer);

      if (query === "") {
        if (state.recipientAbort instanceof AbortController) {
          state.recipientAbort.abort();
        }
        clearRecipientResults();
        setRecipientStatus(defaultRecipientStatus);
        return;
      }

      state.recipientTimer = window.setTimeout(() => {
        void loadRecipients(query);
      }, 140);
    });
  }

  recipientOpenButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (state.recipientAbort instanceof AbortController) {
        state.recipientAbort.abort();
      }
      window.clearTimeout(state.recipientTimer);
      clearRecipientResults();
      setRecipientStatus(defaultRecipientStatus);
      if (recipientInput instanceof HTMLInputElement) {
        recipientInput.value = "";
      }
    });
  });

  document.addEventListener("mouseover", (event) => {
    if (isMobile() || !(event.target instanceof Element)) {
      return;
    }

    const bubble = event.target.closest("[data-message-bubble='1']");
    if (!(bubble instanceof HTMLElement) || !getQuickActionsBar(bubble)) {
      return;
    }

    openQuickActions(bubble);
  });

  document.addEventListener("mouseout", (event) => {
    if (isMobile() || !(event.target instanceof Element)) {
      return;
    }

    const bubble = event.target.closest("[data-message-bubble='1']");
    if (!(bubble instanceof HTMLElement)) {
      return;
    }

    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof Node && bubble.contains(nextTarget)) {
      return;
    }

    scheduleQuickActionsClose(bubble);
  });

  document.addEventListener("pointerdown", (event) => {
    if (!isMobile() || !(event.target instanceof Element)) {
      return;
    }

    const interactiveTarget = event.target.closest(
      "button, a, input, textarea, select, label"
    );
    if (interactiveTarget instanceof HTMLElement) {
      return;
    }

    const bubble = event.target.closest("[data-message-bubble='1']");
    if (!(bubble instanceof HTMLElement) || !getQuickActionsBar(bubble)) {
      return;
    }

    clearLongPress();
    state.longPressBubbleId = getBubbleId(bubble);
    state.longPressTimer = window.setTimeout(() => {
      openQuickActions(bubble, { mobile: true });
      state.longPressBubbleId = getBubbleId(bubble);
    }, 500);
  });

  document.addEventListener("pointerup", clearLongPress);
  document.addEventListener("pointercancel", clearLongPress);
  document.addEventListener(
    "scroll",
    () => {
      clearLongPress();
      if (state.quickActionsMode === "mobile") {
        closeQuickActions();
      }
    },
    true
  );

  document.addEventListener(
    "click",
    (event) => {
      if (!isMobile() || !(event.target instanceof Element)) {
        return;
      }

      const trigger = event.target.closest(
        "[data-dm-message-menu='1'] [data-content-menu-trigger='1']"
      );
      if (!(trigger instanceof HTMLButtonElement)) {
        return;
      }

      const bubble = trigger.closest("[data-message-bubble='1']");
      if (!(bubble instanceof HTMLElement)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      openBubbleActionsSheet(getBubbleId(bubble));
    },
    true
  );

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;

    const attachmentMenu = getAttachmentMenu();
    if (
      attachmentMenu instanceof HTMLElement &&
      !attachmentMenu.contains(event.target)
    ) {
      closeAttachmentMenu();
    }
    const emojiPanel = getComposerEmojiPanel();
    if (emojiPanel instanceof HTMLElement && !emojiPanel.contains(event.target)) {
      const emojiTrigger = event.target.closest("[data-messages-attachment-action='emoji']");
      if (!(emojiTrigger instanceof HTMLButtonElement)) {
        closeEmojiPanel();
      }
    }

    if (!event.target.closest("[data-message-bubble='1']")) {
      closeQuickActions();
    }

    const conversationList = getConversationList();
    const conversationLink = event.target.closest("[data-conversation-item='1']");
    if (
      conversationLink instanceof HTMLAnchorElement &&
      conversationList instanceof HTMLElement &&
      conversationList.contains(conversationLink)
    ) {
      if (!isPlainPrimaryClick(event)) return;
      const nextUrl = toMessagesUrl(conversationLink.href);
      if (!(nextUrl instanceof URL)) return;
      event.preventDefault();
      setThreadOpen(true);
      void loadConversation(nextUrl.toString(), {
        historyMode: "push",
        forceThreadOpen: true,
      });
      return;
    }

    const recipientLink = event.target.closest(
      "[data-message-recipient-results='1'] a.messagesRecipientResult"
    );
    if (recipientLink instanceof HTMLAnchorElement) {
      if (!isPlainPrimaryClick(event)) return;
      const nextUrl = toMessagesUrl(recipientLink.href);
      if (!(nextUrl instanceof URL)) return;
      event.preventDefault();
      closeRecipientSheet();
      setThreadOpen(true);
      void loadConversation(nextUrl.toString(), {
        historyMode: "push",
        forceThreadOpen: true,
        focusComposer: true,
      });
      return;
    }

    const backButton = event.target.closest("[data-thread-back='1']");
    if (backButton instanceof HTMLElement) {
      event.preventDefault();
      const inboxUrl = normalizeToInboxUrl();
      setThreadOpen(false);
      void loadConversation(inboxUrl, {
        historyMode: "replace",
        forceThreadOpen: false,
      }).finally(() => {
        if (searchInput instanceof HTMLInputElement) {
          window.setTimeout(() => {
            searchInput.focus();
          }, 20);
        }
      });
      return;
    }

    const attachmentTrigger = event.target.closest("[data-messages-attachment-trigger='1']");
    if (attachmentTrigger instanceof HTMLButtonElement) {
      event.preventDefault();
      toggleAttachmentMenu();
      return;
    }

    const attachmentAction = event.target.closest("[data-messages-attachment-action]");
    if (attachmentAction instanceof HTMLButtonElement) {
      event.preventDefault();
      const action = String(
        attachmentAction.dataset.messagesAttachmentAction || ""
      ).trim();
      closeAttachmentMenu();

      if (action === "files") {
        const input = getAttachmentInput();
        if (input instanceof HTMLInputElement && !input.disabled) {
          input.click();
        }
        return;
      }

      if (action === "photos") {
        const input = getImageAttachmentInput();
        if (input instanceof HTMLInputElement && !input.disabled) {
          input.click();
        }
        return;
      }

      if (action === "emoji") {
        openEmojiPanel();
        return;
      }

      showComposerPlaceholder(action);
      return;
    }

    const attachmentRemove = event.target.closest("[data-messages-attachment-remove='1']");
    if (attachmentRemove instanceof HTMLButtonElement) {
      event.preventDefault();
      removeSelectedAttachment(String(attachmentRemove.dataset.attachmentKey || ""));
      return;
    }

    const replyClearButton = event.target.closest("[data-messages-reply-clear='1']");
    if (replyClearButton instanceof HTMLButtonElement) {
      event.preventDefault();
      clearComposerReplyContext();
      return;
    }

    const emojiTab = event.target.closest("[data-messages-emoji-tab]");
    if (emojiTab instanceof HTMLButtonElement) {
      event.preventDefault();
      state.emojiCategory = String(emojiTab.dataset.messagesEmojiTab || "smileys");
      renderEmojiPicker();
      return;
    }

    const emojiValue = event.target.closest("[data-messages-emoji-value]");
    if (emojiValue instanceof HTMLButtonElement) {
      event.preventDefault();
      const emoji = String(emojiValue.dataset.messagesEmojiValue || "");
      const input = getComposerInput();
      if (!(input instanceof HTMLTextAreaElement) || !emoji) {
        return;
      }
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? input.value.length;
      const before = input.value.slice(0, start);
      const after = input.value.slice(end);
      input.value = `${before}${emoji}${after}`;
      saveRecentEmoji(emoji);
      renderEmojiPicker();
      syncComposerHeight(input);
      closeEmojiPanel();
      window.setTimeout(() => {
        const caret = start + emoji.length;
        input.focus();
        input.setSelectionRange(caret, caret);
      }, 20);
      return;
    }

    const jumpButton = event.target.closest("[data-message-jump-latest='1']");
    if (jumpButton instanceof HTMLButtonElement) {
      event.preventDefault();
      scrollMessageListToLatest("smooth");
      scheduleMarkConversationRead(80);
      return;
    }

    const loadOlderButton = event.target.closest("[data-message-load-older='1']");
    if (loadOlderButton instanceof HTMLButtonElement) {
      event.preventDefault();
      void loadOlderMessages();
      return;
    }

    const replyJumpButton = event.target.closest("[data-message-reply-jump='1']");
    if (replyJumpButton instanceof HTMLButtonElement) {
      event.preventDefault();
      void jumpToReplyTarget(String(replyJumpButton.dataset.targetMessageId || ""));
      return;
    }

    const quickAction = event.target.closest("[data-message-quick-action]");
    if (quickAction instanceof HTMLButtonElement) {
      event.preventDefault();
      const bubble = quickAction.closest("[data-message-bubble='1']");
      const messageId = getBubbleId(bubble);
      const action = String(quickAction.dataset.messageQuickAction || "");

      if (action === "react") {
        void toggleMessageReaction(messageId);
        if (state.quickActionsMode === "mobile") {
          closeQuickActions(bubble);
        }
        return;
      }
      if (action === "reply") {
        beginReplyToMessage(messageId);
        closeQuickActions(bubble);
        return;
      }
      if (action === "delete") {
        requestDeleteMessage(messageId);
        return;
      }
    }

    const deleteConfirmYes = event.target.closest("[data-message-delete-confirm-yes='1']");
    if (deleteConfirmYes instanceof HTMLButtonElement) {
      event.preventDefault();
      const bubble = deleteConfirmYes.closest("[data-message-bubble='1']");
      const messageId = getBubbleId(bubble);
      hideDeleteConfirmation(bubble);
      void performDeleteMessage(messageId);
      return;
    }

    const deleteConfirmNo = event.target.closest("[data-message-delete-confirm-no='1']");
    if (deleteConfirmNo instanceof HTMLButtonElement) {
      event.preventDefault();
      const bubble = deleteConfirmNo.closest("[data-message-bubble='1']");
      hideDeleteConfirmation(bubble);
      if (state.quickActionsMode !== "mobile") {
        scheduleQuickActionsClose(bubble, 80);
      }
      return;
    }

    const copyButton = event.target.closest("[data-message-copy='1']");
    if (copyButton instanceof HTMLButtonElement) {
      event.preventDefault();
      void copyMessageBody(String(copyButton.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const editButton = event.target.closest("[data-message-edit='1']");
    if (editButton instanceof HTMLButtonElement) {
      event.preventDefault();
      enterMessageEditMode(String(editButton.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const unsendButton = event.target.closest("[data-message-unsend='1']");
    if (unsendButton instanceof HTMLButtonElement) {
      event.preventDefault();
      closeSheet("message-bubble-actions");
      requestDeleteMessage(String(unsendButton.dataset.messageId || ""));
      return;
    }

    const timeButton = event.target.closest("[data-message-show-time='1']");
    if (timeButton instanceof HTMLButtonElement) {
      event.preventDefault();
      showMessageTimestamp(String(timeButton.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const retryButton = event.target.closest("[data-message-retry='1']");
    if (retryButton instanceof HTMLButtonElement) {
      event.preventDefault();
      void retryFailedMessage(String(retryButton.dataset.tempMessageId || ""));
      return;
    }

    const editCancelButton = event.target.closest("[data-message-edit-cancel='1']");
    if (editCancelButton instanceof HTMLButtonElement) {
      event.preventDefault();
      exitMessageEditMode();
      return;
    }

    const sheetCopy = event.target.closest("[data-message-sheet-copy='1']");
    if (sheetCopy instanceof HTMLButtonElement) {
      event.preventDefault();
      const sheet = getBubbleActionsSheet();
      void copyMessageBody(String(sheet?.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const sheetEdit = event.target.closest("[data-message-sheet-edit='1']");
    if (sheetEdit instanceof HTMLButtonElement) {
      event.preventDefault();
      const sheet = getBubbleActionsSheet();
      enterMessageEditMode(String(sheet?.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const sheetUnsend = event.target.closest("[data-message-sheet-unsend='1']");
    if (sheetUnsend instanceof HTMLButtonElement) {
      event.preventDefault();
      const sheet = getBubbleActionsSheet();
      closeSheet("message-bubble-actions");
      requestDeleteMessage(String(sheet?.dataset.messageId || ""));
      return;
    }

    const sheetReport = event.target.closest("[data-message-sheet-report='1']");
    if (sheetReport instanceof HTMLButtonElement) {
      event.preventDefault();
      const sheet = getBubbleActionsSheet();
      const messageId = String(sheet?.dataset.messageId || "");
      closeSheet("message-bubble-actions");
      window.setTimeout(() => {
        openReportForMessage(messageId);
      }, 0);
      return;
    }

    const sheetTime = event.target.closest("[data-message-sheet-time='1']");
    if (sheetTime instanceof HTMLButtonElement) {
      event.preventDefault();
      const sheet = getBubbleActionsSheet();
      showMessageTimestamp(String(sheet?.dataset.messageId || ""));
      closeSheet("message-bubble-actions");
      return;
    }

    const retryTransport = event.target.closest("[data-thread-transport-retry='1']");
    if (retryTransport instanceof HTMLButtonElement) {
      event.preventDefault();
      if (state.threadTransportRetryUrl) {
        void loadConversation(state.threadTransportRetryUrl, {
          historyMode: "none",
          forceThreadOpen: urlHasThreadState(state.threadTransportRetryUrl),
        });
      }
    }
  });

  document.addEventListener("change", (event) => {
    const input = event.target;
    if (
      input instanceof HTMLInputElement &&
      input.matches("[data-messages-attachment-input='1']")
    ) {
      addSelectedAttachments(input.files);
      input.value = "";
      closeAttachmentMenu();
      return;
    }

    if (
      input instanceof HTMLInputElement &&
      input.matches("[data-messages-image-input='1']")
    ) {
      addSelectedAttachments(input.files);
      input.value = "";
      closeAttachmentMenu();
    }
  });

  document.addEventListener("input", (event) => {
    const input = event.target;
    if (
      input instanceof HTMLInputElement &&
      input.matches("[data-messages-emoji-search='1']")
    ) {
      renderEmojiPicker();
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (form.dataset.messagesComposer === "1") {
      event.preventDefault();
      void submitComposer(form);
      return;
    }

    if (form.matches("[data-message-edit-form='1']")) {
      event.preventDefault();
      void submitMessageEdit(form);
      return;
    }

    try {
      const actionUrl = new URL(form.action, window.location.origin);
      if (/\/mark_conversation_read\.php$/i.test(actionUrl.pathname)) {
        event.preventDefault();
        const input = form.querySelector("input[name='id']");
        const conversationId =
          input instanceof HTMLInputElement ? Number(input.value || "0") : 0;
        void markConversationRead(conversationId);
      }
    } catch {
      // Ignore malformed action URLs and let the browser submit normally.
    }
  });

  document.addEventListener("compositionstart", (event) => {
    const input = event.target;
    if (
      input instanceof HTMLTextAreaElement &&
      input.matches("[data-messages-input='1']")
    ) {
      input.dataset.imeComposing = "1";
    }
  });

  document.addEventListener("compositionend", (event) => {
    const input = event.target;
    if (
      input instanceof HTMLTextAreaElement &&
      input.matches("[data-messages-input='1']")
    ) {
      delete input.dataset.imeComposing;
    }
  });

  document.addEventListener("keydown", (event) => {
    const input = event.target;

    if (
      input instanceof HTMLTextAreaElement &&
      input.matches("[data-message-edit-input='1']") &&
      event.key === "Escape"
    ) {
      event.preventDefault();
      exitMessageEditMode();
      return;
    }

    if (
      !(input instanceof HTMLTextAreaElement) ||
      !input.matches("[data-messages-input='1']")
    ) {
      return;
    }

    if (event.defaultPrevented || event.key !== "Enter" || event.shiftKey) {
      return;
    }
    if (event.isComposing || input.dataset.imeComposing === "1") {
      return;
    }

    const mentionHost = input.closest(".field") || input.parentElement;
    const mentionPanel =
      mentionHost instanceof HTMLElement
        ? mentionHost.querySelector("[data-mention-panel='1']")
        : null;
    const mentionPanelOpen =
      mentionPanel instanceof HTMLElement &&
      !mentionPanel.hidden &&
      mentionPanel.querySelector("[data-mention-option='1']");
    if (mentionPanelOpen) {
      return;
    }

    const form = input.closest("[data-messages-composer='1']");
    if (!(form instanceof HTMLFormElement) || isComposerBusy(form)) {
      return;
    }

    const trimmedBody = input.value.trim();
    event.preventDefault();

    if (trimmedBody === "" && state.selectedAttachments.length < 1) {
      showToast("Message must contain text or attachments.", "error");
      return;
    }

    if (typeof form.requestSubmit === "function") {
      form.requestSubmit();
      return;
    }

    form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") {
      stopPolling();
      return;
    }
    syncThreadStateFromDom();
    scheduleMarkConversationRead(80);
    schedulePoll(2500);
    void pollConversation();
  });

  window.addEventListener("focus", () => {
    scheduleMarkConversationRead(80);
    schedulePoll(2500);
  });

  window.addEventListener("popstate", () => {
    const nextUrl = toMessagesUrl(window.location.href);
    if (!(nextUrl instanceof URL)) return;

    void loadConversation(nextUrl.toString(), {
      historyMode: "none",
      forceThreadOpen: urlHasThreadState(nextUrl.toString()),
    });
  });

  if (mobileQuery instanceof MediaQueryList) {
    mobileQuery.addEventListener("change", () => {
      body.classList.toggle(
        "messages-thread-active",
        layout.classList.contains("is-thread-open")
      );
    });
  }
})();
