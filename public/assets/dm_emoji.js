(() => {
  const CATALOG_PATH = "/assets/emoji/noto/catalog.json";

  let catalogPromise = null;
  let catalogCache = null;

  const escapeHtml = (value) =>
    String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const normalizeText = (value) =>
    String(value || "")
      .toLowerCase()
      .normalize("NFKD");

  const trimTrailingSlash = (value) => String(value || "").replace(/\/$/, "");

  const assetUrl = (baseUrl, assetPath) =>
    `${trimTrailingSlash(baseUrl)}${CATALOG_PATH.replace("catalog.json", "")}${String(
      assetPath || ""
    ).replace(/^\//, "")}`;

  const buildTrie = (items) => {
    const root = new Map();
    items.forEach((item) => {
      let node = root;
      let branch = null;
      Array.from(item.emoji).forEach((symbol) => {
        if (!node.has(symbol)) {
          node.set(symbol, { children: new Map(), item: null });
        }
        branch = node.get(symbol);
        node = branch.children;
      });
      if (branch) {
        branch.item = item;
      }
    });
    return root;
  };

  const attachVariantItems = (entries, categoryMeta, baseUrl) =>
    entries.map((entry) => toCatalogItem(entry, categoryMeta, baseUrl));

  const toCatalogItem = (entry, categoryMeta, baseUrl) => {
    const keywords = Array.isArray(entry?.keywords)
      ? entry.keywords.map((value) => String(value || "")).filter(Boolean)
      : [];
    const item = {
      emoji: String(entry?.emoji || ""),
      codepoints: Array.isArray(entry?.codepoints) ? entry.codepoints.slice() : [],
      name: String(entry?.name || "Emoji"),
      keywords,
      assetPath: String(entry?.asset_path || ""),
      assetUrl: assetUrl(baseUrl, entry?.asset_path || ""),
      categoryId: String(categoryMeta?.id || ""),
      categoryLabel: String(categoryMeta?.label || ""),
      searchText: normalizeText(
        [
          entry?.emoji || "",
          entry?.name || "",
          keywords.join(" "),
          categoryMeta?.label || "",
        ].join(" ")
      ),
      variants: [],
    };
    item.variants = attachVariantItems(entry?.variants || [], categoryMeta, baseUrl);
    return item;
  };

  const flattenCatalogItems = (items, allItems, byEmoji) => {
    items.forEach((item) => {
      allItems.push(item);
      byEmoji.set(item.emoji, item);
      if (Array.isArray(item.variants) && item.variants.length) {
        flattenCatalogItems(item.variants, allItems, byEmoji);
      }
    });
  };

  const prepareCatalog = (data, baseUrl) => {
    const categories = Array.isArray(data?.categories)
      ? data.categories.map((category) => {
          const meta = {
            id: String(category?.id || ""),
            label: String(category?.label || ""),
            iconEmoji: String(category?.icon_emoji || ""),
            iconAssetPath: String(category?.icon_asset_path || ""),
            iconAssetUrl: assetUrl(baseUrl, category?.icon_asset_path || ""),
          };
          return {
            ...meta,
            items: attachVariantItems(category?.items || [], meta, baseUrl),
          };
        })
      : [];

    const allItems = [];
    const byEmoji = new Map();
    categories.forEach((category) => flattenCatalogItems(category.items, allItems, byEmoji));

    const prepared = {
      categories,
      categoryMap: new Map(categories.map((category) => [category.id, category])),
      allItems,
      byEmoji,
      trie: buildTrie(allItems),
    };

    return prepared;
  };

  const loadCatalog = async (baseUrl) => {
    if (catalogCache) {
      return catalogCache;
    }
    if (!catalogPromise) {
      catalogPromise = fetch(assetUrl(baseUrl, "catalog.json"), {
        headers: { Accept: "application/json" },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Could not load emoji catalog.");
          }
          return response.json();
        })
        .then((data) => {
          catalogCache = prepareCatalog(data, baseUrl);
          return catalogCache;
        })
        .catch((error) => {
          catalogPromise = null;
          throw error;
        });
    }
    return catalogPromise;
  };

  const getCatalog = () => catalogCache;

  const findItem = (emoji) => {
    if (!catalogCache) {
      return null;
    }
    return catalogCache.byEmoji.get(String(emoji || "")) || null;
  };

  const renderEmojiImageHtml = (item, options = {}) => {
    if (!item || !item.assetUrl) {
      return "";
    }
    const className = String(options.className || "").trim();
    const decorative = options.decorative !== false;
    const alt = decorative ? "" : item.emoji;
    const ariaHidden = decorative ? ' aria-hidden="true"' : "";
    const loading = options.loading === false ? "" : ' loading="lazy" decoding="async"';
    const title = options.title === false ? "" : ` title="${escapeHtml(item.name)}"`;
    return `<img class="${escapeHtml(className)}" src="${escapeHtml(
      item.assetUrl
    )}" alt="${escapeHtml(alt)}"${ariaHidden}${loading}${title} draggable="false">`;
  };

  const renderInlineTextHtml = (value, options = {}) => {
    const text = String(value || "");
    if (!text) {
      return "";
    }
    if (!catalogCache) {
      return escapeHtml(text);
    }

    const symbols = Array.from(text);
    const pieces = [];
    let plain = "";
    let index = 0;

    while (index < symbols.length) {
      let node = catalogCache.trie;
      let match = null;
      let cursor = index;

      while (cursor < symbols.length) {
        const branch = node.get(symbols[cursor]);
        if (!branch) {
          break;
        }
        if (branch.item) {
          match = { item: branch.item, end: cursor + 1 };
        }
        node = branch.children;
        cursor += 1;
      }

      if (match) {
        if (plain) {
          pieces.push(escapeHtml(plain));
          plain = "";
        }
        pieces.push(
          `<span class="${escapeHtml(
            String(options.wrapperClassName || "messageBubble__emojiInline")
          )}">${renderEmojiImageHtml(match.item, {
            className: String(options.className || "messageBubble__emojiImage"),
            decorative: false,
          })}</span>`
        );
        index = match.end;
        continue;
      }

      plain += symbols[index];
      index += 1;
    }

    if (plain) {
      pieces.push(escapeHtml(plain));
    }

    return pieces.join("");
  };

  window.TruxDmEmoji = {
    loadCatalog,
    getCatalog,
    findItem,
    normalizeText,
    renderEmojiImageHtml,
    renderInlineTextHtml,
  };
})();
