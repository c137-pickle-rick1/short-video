const modal = document.querySelector("[data-profile-social-modal='true']");
const modalPanel = document.querySelector("[data-profile-social-panel='true']");
const triggerButtons = Array.from(document.querySelectorAll("[data-profile-social-trigger]"));
const closeButtons = Array.from(document.querySelectorAll("[data-profile-social-close='true']"));
const tabButtons = Array.from(document.querySelectorAll("[data-profile-social-tab-button]"));
const tabPanels = Array.from(document.querySelectorAll("[data-profile-social-tab-panel]"));

if (modal instanceof HTMLElement && modalPanel instanceof HTMLElement && triggerButtons.length > 0) {
  let previousBodyOverflow = "";
  let lastActiveElement = null;

  function setTab(tabKey) {
    for (const button of tabButtons) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      const isActive = button.dataset.profileSocialTabButton === tabKey;
      button.dataset.active = isActive ? "true" : "false";
      button.className = [
        "relative pb-4 text-left text-xl font-semibold tracking-tight transition sm:text-2xl",
        isActive ? "text-gray-950" : "text-gray-400 hover:text-gray-700"
      ].join(" ");

      const indicator = button.querySelector("span.absolute");
      if (indicator instanceof HTMLElement) {
        indicator.hidden = !isActive;
      }
    }

    for (const panel of tabPanels) {
      if (!(panel instanceof HTMLElement)) {
        continue;
      }

      const isActive = panel.dataset.profileSocialTabPanel === tabKey;
      panel.dataset.active = isActive ? "true" : "false";
      panel.hidden = !isActive;
    }
  }

  function openModal(tabKey) {
    if (modal.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setTab(tabKey);
    modal.hidden = false;
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    modalPanel.focus({ preventScroll: true });
  }

  function closeModal() {
    if (modal.hidden) {
      return;
    }

    modal.classList.add("hidden");
    modal.hidden = true;
    document.body.style.overflow = previousBodyOverflow;

    if (lastActiveElement instanceof HTMLElement) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
  }

  for (const trigger of triggerButtons) {
    if (!(trigger instanceof HTMLButtonElement)) {
      continue;
    }

    trigger.addEventListener("click", () => {
      openModal(String(trigger.dataset.profileSocialTrigger || "following"));
    });
  }

  for (const closeButton of closeButtons) {
    if (!(closeButton instanceof HTMLButtonElement)) {
      continue;
    }

    closeButton.addEventListener("click", () => {
      closeModal();
    });
  }

  for (const tabButton of tabButtons) {
    if (!(tabButton instanceof HTMLButtonElement)) {
      continue;
    }

    tabButton.addEventListener("click", () => {
      setTab(String(tabButton.dataset.profileSocialTabButton || "following"));
    });
  }

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeModal();
    }
  });

  const initialTabButton = tabButtons.find(
    (button) => button instanceof HTMLButtonElement && button.dataset.active === "true"
  );

  setTab(
    initialTabButton instanceof HTMLButtonElement
      ? String(initialTabButton.dataset.profileSocialTabButton || "following")
      : "following"
  );
}
