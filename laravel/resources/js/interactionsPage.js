import { requestJson } from "./app/http";

function interactionPageElements() {
  return {
    backButton: document.querySelector("[data-interaction-back='true']"),
    list: document.querySelector("[data-interaction-list='true']"),
    pagination: document.querySelector("[data-interaction-pagination='true']"),
    emptyState: document.querySelector("[data-interaction-empty-state='true']"),
    feedback: document.querySelector("[data-interaction-feedback='true']"),
    totalCountSlots: [...document.querySelectorAll("[data-interaction-total-count='true']")]
  };
}

function setInteractionTotalCount(totalCount) {
  const { list, totalCountSlots } = interactionPageElements();
  const normalizedTotalCount = Math.max(0, totalCount);

  if (list instanceof HTMLElement) {
    list.dataset.interactionTotalCount = String(normalizedTotalCount);
  }

  for (const totalCountSlot of totalCountSlots) {
    totalCountSlot.textContent = String(normalizedTotalCount);
  }
}

function getInteractionTotalCount() {
  const { list } = interactionPageElements();
  if (!(list instanceof HTMLElement)) {
    return 0;
  }

  const totalCount = Number.parseInt(list.dataset.interactionTotalCount || "0", 10);
  return Number.isNaN(totalCount) ? 0 : totalCount;
}

function updateInteractionPageState() {
  const { list, emptyState } = interactionPageElements();
  if (!(list instanceof HTMLElement)) {
    return;
  }

  const items = [...list.querySelectorAll("[data-interaction-item='true']")];
  list.hidden = items.length === 0;

  if (emptyState) {
    emptyState.hidden = items.length !== 0;
  }

  updateInteractionPaginationState();
}

function updateInteractionPaginationState() {
  const { list, pagination } = interactionPageElements();
  if (!(list instanceof HTMLElement) || !(pagination instanceof HTMLElement)) {
    return;
  }

  const totalCount = getInteractionTotalCount();
  const perPage = Number.parseInt(list.dataset.interactionPerPage || "12", 10);
  pagination.hidden = totalCount <= perPage;
}

function showInteractionFeedback(message) {
  const { feedback } = interactionPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = message;
  feedback.hidden = false;
  feedback.classList.remove("hidden");
}

function clearInteractionFeedback() {
  const { feedback } = interactionPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = "";
  feedback.hidden = true;
  feedback.classList.add("hidden");
}

async function handleInteractionActionClick(button) {
  const item = button.closest("[data-interaction-item='true']");
  if (!(item instanceof HTMLElement)) {
    return;
  }

  clearInteractionFeedback();

  const actionUrl = String(button.dataset.actionUrl || "").trim();
  const loadingLabel = String(button.dataset.loadingLabel || "处理中...").trim() || "处理中...";
  const originalMarkup = button.innerHTML;

  if (actionUrl === "") {
    return;
  }

  button.disabled = true;
  button.innerHTML = `<span class="text-xs font-semibold">${loadingLabel}</span>`;

  try {
    await requestJson(actionUrl, {
      method: "DELETE"
    });

    item.remove();
    setInteractionTotalCount(getInteractionTotalCount() - 1);

    const { list } = interactionPageElements();
    const remainingItems = list ? [...list.querySelectorAll("[data-interaction-item='true']")] : [];
    const previousPageUrl =
      list instanceof HTMLElement ? String(list.dataset.interactionPreviousPageUrl || "").trim() : "";

    if (remainingItems.length === 0 && previousPageUrl !== "") {
      window.location.assign(previousPageUrl);
      return;
    }

    updateInteractionPageState();
  } catch (error) {
    const message = error instanceof Error ? error.message : "操作失败，请稍后再试。";
    showInteractionFeedback(message);
    button.disabled = false;
    button.innerHTML = originalMarkup;
  }
}

function bindInteractionActionButtons() {
  for (const button of document.querySelectorAll("[data-interaction-action='true']")) {
    button.addEventListener("click", () => {
      void handleInteractionActionClick(button);
    });
  }
}

function bindInteractionBackButton() {
  const { backButton } = interactionPageElements();
  if (!(backButton instanceof HTMLButtonElement)) {
    return;
  }

  backButton.addEventListener("click", () => {
    const fallbackUrl = String(backButton.dataset.fallbackUrl || "/me").trim() || "/me";

    if (window.history.length > 1) {
      window.history.back();
      return;
    }

    window.location.assign(fallbackUrl);
  });
}

updateInteractionPageState();
bindInteractionActionButtons();
bindInteractionBackButton();
