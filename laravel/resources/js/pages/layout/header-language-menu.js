const languageMenus = Array.from(document.querySelectorAll('[data-language-menu="true"]'));

function setupLanguageMenu(menuRoot) {
  const trigger = menuRoot.querySelector('[data-language-menu-trigger="true"]');
  const panel = menuRoot.querySelector('[data-language-menu-panel="true"]');
  const currentLabel = menuRoot.querySelector('[data-language-menu-current-label="true"]');
  const chevron = menuRoot.querySelector('[data-language-menu-chevron="true"]');
  const items = Array.from(menuRoot.querySelectorAll('[data-language-menu-item="true"]'));

  if (!(trigger instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) {
    return;
  }

  const setOpen = (shouldOpen) => {
    trigger.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    panel.hidden = !shouldOpen;
    panel.classList.toggle("hidden", !shouldOpen);
    panel.setAttribute("aria-hidden", shouldOpen ? "false" : "true");

    if (chevron instanceof HTMLElement) {
      chevron.classList.toggle("rotate-180", shouldOpen);
    }
  };

  const syncSelectedItem = (nextItem) => {
    items.forEach((item) => {
      const isCurrent = item === nextItem;
      item.dataset.current = isCurrent ? "true" : "false";
      item.setAttribute("aria-checked", isCurrent ? "true" : "false");
      item.classList.toggle("font-semibold", isCurrent);
      item.classList.toggle("text-gray-900", isCurrent);
      item.classList.toggle("font-normal", !isCurrent);
      item.classList.toggle("text-gray-700", !isCurrent);

      const check = item.querySelector('[data-language-menu-check="true"]');

      if (check instanceof HTMLElement) {
        check.hidden = !isCurrent;
        check.classList.toggle("hidden", !isCurrent);
      }
    });

    if (currentLabel instanceof HTMLElement) {
      currentLabel.textContent = nextItem.dataset.languageLabel || nextItem.textContent?.trim() || "";
    }
  };

  const activeItem = items.find((item) => item.dataset.current === "true") || items[0];

  if (activeItem instanceof HTMLButtonElement) {
    syncSelectedItem(activeItem);
  }

  trigger.addEventListener("click", (event) => {
    event.stopPropagation();
    setOpen(panel.hidden);
  });

  items.forEach((item) => {
    item.addEventListener("click", () => {
      syncSelectedItem(item);
      setOpen(false);
    });
  });

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Node) || menuRoot.contains(event.target)) {
      return;
    }

    setOpen(false);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || panel.hidden) {
      return;
    }

    setOpen(false);
    trigger.focus();
  });
}

languageMenus.forEach(setupLanguageMenu);
