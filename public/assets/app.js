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